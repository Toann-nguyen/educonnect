<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserRepository implements UserRepositoryInterface
{
    protected $model;

    public function __construct(User $model)
    {
        $this->model = $model;
    }

    public function allUser(array $filters = [])
    {
        $query = $this->model->newQuery();

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['search'])) {
            $query->whereHas('profile', function ($q) use ($filters) {
                $q->where('full_name', 'LIKE', '%' . $filters['search'] . '%');
            });
        }
        // Apply filters if any
        if (isset($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        return $query->with('profile', 'roles')->get();
    }
    /**
     * Tìm một người dùng bằng địa chỉ email.
     *
     * @param string $email
     * @return User|null
     */
    public function finByEmailUser(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    /**
     * Tạo một người dùng mới cùng với hồ sơ cá nhân.
     *
     * @param array $data
     * @return User
     */
    public function createUser(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'email' => $data['email'],
                'password' => Hash::make($data['password']), // Sửa từ FacadesHash thành Hash
            ]);
            $user->profile()->create(['full_name' => $data['full_name']]);
            return $user;
        });
    }
    public function find(int $id)
    {
        return User::with('profile', 'roles')->find($id);
    }
    public function paginate(int $perPage = 15, array $filters = [])
    {
        // Logic lọc sẽ được thêm vào đây
        return User::with('profile', 'roles')->paginate($perPage);
    }

    public function updateUser(int $id, array $data)
    {
        return DB::transaction(function () use ($id, $data) {
            $user = $this->find($id);
            if (!$user) {
                throw new Exception('User not found.');
            }

            // Cập nhật bảng users
            if (isset($data['email']) || isset($data['password'])) {
                $userData = [];
                if (isset($data['email'])) $userData['email'] = $data['email'];
                if (isset($data['password'])) $userData['password'] = Hash::make($data['password']);
                $user->update($userData);
            }

            // Cập nhật bảng profiles
            if (isset($data['full_name'])) {
                $user->profile()->update([
                    'full_name' => $data['full_name']
                ]);
            }

            return $user->load('profile');
        });
    }

    public function deleteUser(int $id)
    {
        $user = $this->find($id);
        if (!$user) return false;

        return $user->delete(); // Soft delete
    }

    public function restoreUser(int $id)
    {
        $user = User::onlyTrashed()->find($id);
        if (!$user) return false;

        return $user->restore();
    }

    public function forceDeleteUser(int $id)
    {
        $user = User::withTrashed()->find($id);
        if (!$user) return false;

        return $user->forceDelete();
    }
}
