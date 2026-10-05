<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class TransactionNumberService
{
    // Called only within the same transaction as the stock operation.
    public function next(string $type): string
    {
        $type = $type === 'ADJUSTMENT' ? 'ADJ' : $type;
        $date = now()->toDateString();
        DB::table('transaction_sequences')->insertOrIgnore(['sequence_date' => $date, 'type' => $type, 'last_number' => 0]);
        $q = DB::table('transaction_sequences')->where('sequence_date', $date)->where('type', $type);
        $row = (clone $q)->lockForUpdate()->first();
        $n = $row->last_number + 1;
        $q->update(['last_number' => $n]);

        return $type.'-'.now()->format('Ymd').'-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}
