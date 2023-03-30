<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class AnalysisService
{
    public static function perDay($subQuery)
    {
        $query = $subQuery->where('status', true)
            ->groupBy('id')
            ->selectRaw('id, sum(subtotal) as totalPerPurchase, 
      DATE_FORMAT(created_at, "%Y%m%d") as date');

        $data = DB::table($query)
            ->groupBy('date')
            ->selectRaw('date, sum(totalPerPurchase) as total')
            ->get();

        $labels = $data->pluck('date');
        $totals = $data->pluck('total');

        return [$data, $labels, $totals];
    }
}
