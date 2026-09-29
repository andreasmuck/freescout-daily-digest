<?php

namespace Modules\DailyDigest\Services;

use Illuminate\Database\QueryException;

class DeliveryLedger
{
    const PREFIX = 'dailydigest.delivery.';

    public function key($userId, $date)
    {
        return self::PREFIX.$date.'.'.(int) $userId;
    }

    public function exists($userId, $date)
    {
        return \DB::table('options')->where('name', $this->key($userId, $date))->exists();
    }

    public function claim($userId, $date, $count)
    {
        // options.name has a unique database index in FreeScout 1.8.241.
        // Claim before SMTP: an interrupted/ambiguous send must not be retried blindly.
        try {
            \DB::table('options')->insert([
                'name' => $this->key($userId, $date),
                'value' => json_encode(['status' => 'claimed', 'count' => $count, 'at' => gmdate('c')]),
            ]);
            return true;
        } catch (QueryException $e) {
            if ($this->exists($userId, $date)) {
                return false;
            }
            throw $e;
        }
    }

    public function finish($userId, $date, $status, $count)
    {
        \DB::table('options')->where('name', $this->key($userId, $date))->update([
            'value' => json_encode(['status' => $status, 'count' => $count, 'at' => gmdate('c')]),
        ]);
    }

    public function prune($beforeDate)
    {
        \DB::table('options')->where('name', '>=', self::PREFIX)
            ->where('name', '<', self::PREFIX.$beforeDate)->delete();
    }
}
