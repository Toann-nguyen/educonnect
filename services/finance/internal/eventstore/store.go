package eventstore

import (
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"time"

	"gorm.io/gorm"
	"gorm.io/gorm/clause"
)

type Event struct {
	ID            uint64         `gorm:"primaryKey;autoIncrement"`
	AggregateType string         `gorm:"size:50;not null"`
	AggregateID   uint64         `gorm:"not null"`
	Version       uint32         `gorm:"not null"`
	EventType     string         `gorm:"size:100;not null"`
	Payload       map[string]any `gorm:"type:json;not null"`
	Metadata      map[string]any `gorm:"type:json"`
	OccurredAt    time.Time      `gorm:"not null"`
	CreatedAt     time.Time      `gorm:"not null"`
}

func (Event) TableName() string { return "event_store" }

type Snapshot struct {
	AggregateType   string         `gorm:"size:50;primaryKey"`
	AggregateID     uint64         `gorm:"primaryKey"`
	Version         uint32         `gorm:"primaryKey"`
	SnapshotPayload map[string]any `gorm:"type:json;not null"`
	CreatedAt       time.Time      `gorm:"not null"`
}

func (Snapshot) TableName() string { return "event_snapshot" }

type Store struct {
	db *gorm.DB
}

func NewStore(db *gorm.DB) *Store { return &Store{db: db} }

func (s *Store) Append(ctx context.Context, event Event) (Event, error) {
	if event.AggregateType == "" || event.AggregateID == 0 || event.EventType == "" {
		return Event{}, errors.New("aggregate type, aggregate id, and event type are required")
	}
	if event.Payload == nil {
		return Event{}, errors.New("event payload is required")
	}
	if event.OccurredAt.IsZero() {
		event.OccurredAt = time.Now().UTC()
	}

	var saved Event
	err := s.db.WithContext(ctx).Transaction(func(tx *gorm.DB) error {
		var current uint32
		if err := tx.Model(&Event{}).
			Where("aggregate_type = ? AND aggregate_id = ?", event.AggregateType, event.AggregateID).
			Select("COALESCE(MAX(version), 0)").Clauses(clause.Locking{Strength: "UPDATE"}).Scan(&current).Error; err != nil {
			return err
		}
		event.Version = current + 1
		if err := tx.Create(&event).Error; err != nil {
			return err
		}
		saved = event
		if event.Version%100 != 0 {
			return nil
		}
		return tx.Create(&Snapshot{
			AggregateType:   event.AggregateType,
			AggregateID:     event.AggregateID,
			Version:         event.Version,
			SnapshotPayload: event.Payload,
			CreatedAt:       time.Now().UTC(),
		}).Error
	})
	if err != nil {
		return Event{}, fmt.Errorf("append event: %w", err)
	}
	return saved, nil
}

func (s *Store) Read(ctx context.Context, aggregateType string, aggregateID uint64, fromVersion uint32) ([]Event, error) {
	var events []Event
	err := s.db.WithContext(ctx).Where("aggregate_type = ? AND aggregate_id = ? AND version > ?", aggregateType, aggregateID, fromVersion).
		Order("version ASC").Find(&events).Error
	return events, err
}

func (s *Store) LatestSnapshot(ctx context.Context, aggregateType string, aggregateID uint64) (Snapshot, error) {
	var snapshot Snapshot
	err := s.db.WithContext(ctx).Where("aggregate_type = ? AND aggregate_id = ?", aggregateType, aggregateID).
		Order("version DESC").First(&snapshot).Error
	return snapshot, err
}

func Encode(payload any) (map[string]any, error) {
	data, err := json.Marshal(payload)
	if err != nil {
		return nil, err
	}
	var result map[string]any
	if err := json.Unmarshal(data, &result); err != nil {
		return nil, err
	}
	return result, nil
}
