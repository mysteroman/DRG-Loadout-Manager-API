<?php namespace Models\Brokers;

use Zephyrus\Security\Cryptography;

class UserBroker extends Broker
{
    public function tryCreateUser(string $username, string $email, string $password): bool
    {
        $password = Cryptography::hashPassword($password);
        $sql = 'insert into "user" (username, email, password) values (?, ?, ?)';
        try
        {
            $this->query($sql, [$username, $email, $password]);
            return true;
        }
        catch (\Exception)
        {
            return false;
        }
    }

    public function authUser(string $username, string $password): ?\stdClass
    {
        $sql = 'select id, username, password from "user" where username = ?';
        $user = $this->selectSingle($sql, [$username]);
        if (is_null($user)) return null;
        if (!Cryptography::verifyHashedPassword($password, $user->password)) return null;
        unset($user->password);
        $user->api_key = $this->getUserKey($user->id);
        unset($user->id);
        return $user;
    }

    public function getUserIdFromKey(string $key): ?int
    {
        $sql = 'select id_user "id" from user_key where api_key = ?';
        $user = $this->selectSingle($sql, [$key]);
        if (is_null($user)) return null;
        return $user->id;
    }

    public function getUserFollows(string $key): array
    {
        $sql = 'select u.username "user.username" from user_key k join user_follow f on f.id_follower = k.id_user join "user" u on u.id = f.id_followed where k.api_key = ?';
        return $this->select($sql, [$key], function ($user) { return $user->username; });
    }

    public function doesUserFollow(string $key, string $username): bool
    {
        $sql = 'select * from user_key k join user_follow f on f.id_follower = k.id_user join "user" u on u.id = f.id_followed where k.api_key = ? and u.username = ?';
        return !is_null($this->selectSingle($sql, [$key, $username]));
    }

    private function getUserKey(int $user_id): string
    {
        $sql = "select api_key from user_key where id_user = ?";
        $key = $this->selectSingle($sql, [$user_id]);
        if (!is_null($key)) return $key->api_key;

        $sql = "select * from user_key where api_key = ?";
        do
        {
            $key = Cryptography::hash(Cryptography::randomBytes(32), 'sha256');
        } while (!is_null($this->selectSingle($sql, [$key])));

        $sql = 'insert into user_key (id_user, api_key) values (?, ?)';
        $this->query($sql, [$user_id, $key]);
        return $key;
    }
}