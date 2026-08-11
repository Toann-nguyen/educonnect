package template

import (
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"time"

	"github.com/redis/go-redis/v9"
)

const keyPrefix = "notify:templates:"

// Template — mẫu thông báo lưu trên Redis.
type Template struct {
	Name      string    `json:"name" example:"welcome_email" validate:"required"`
	Subject   string    `json:"subject" example:"Chào mừng đến EduConnect" validate:"required"`
	Body      string    `json:"body" example:"Xin chào {{name}}, cảm ơn bạn đã đăng ký!" validate:"required"`
	Channels  []string  `json:"channels" example:"email"`
	UpdatedAt time.Time `json:"updated_at"`
}

type Store struct {
	rdb *redis.Client
}

func NewStore(rdb *redis.Client) *Store {
	return &Store{rdb: rdb}
}

func (s *Store) key(name string) string {
	return keyPrefix + name
}

func (s *Store) List(ctx context.Context) ([]Template, error) {
	keys, err := s.rdb.Keys(ctx, keyPrefix+"*").Result()
	if err != nil {
		return nil, err
	}
	out := make([]Template, 0, len(keys))
	for _, k := range keys {
		raw, err := s.rdb.Get(ctx, k).Result()
		if err != nil {
			if errors.Is(err, redis.Nil) {
				continue
			}
			return nil, err
		}
		var t Template
		if err := json.Unmarshal([]byte(raw), &t); err != nil {
			continue
		}
		out = append(out, t)
	}
	return out, nil
}

func (s *Store) Get(ctx context.Context, name string) (*Template, error) {
	raw, err := s.rdb.Get(ctx, s.key(name)).Result()
	if err != nil {
		if errors.Is(err, redis.Nil) {
			return nil, nil
		}
		return nil, err
	}
	var t Template
	if err := json.Unmarshal([]byte(raw), &t); err != nil {
		return nil, fmt.Errorf("decode template %s: %w", name, err)
	}
	return &t, nil
}

func (s *Store) Save(ctx context.Context, t *Template) error {
	t.UpdatedAt = time.Now()
	raw, err := json.Marshal(t)
	if err != nil {
		return err
	}
	return s.rdb.Set(ctx, s.key(t.Name), raw, 0).Err()
}
