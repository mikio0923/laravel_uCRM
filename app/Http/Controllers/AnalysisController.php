<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class AnalysisController extends Controller
{
    public function index()
    {
        $startDate = '2022-08-01';
        $endDate = '2022-08-10';

        return Inertia::render('Analysis');
    }

    public function decile()
    {
        $startDate = '2022-08-01';
        $endDate = '2022-08-10';

        $subQuery = Order::betweenDate($startDate, $endDate)
            ->groupBy('id')
            ->selectRaw('id, customer_id, customer_name, 
            SUM(subtotal) as totalPerPurchase');

        $subQuery = DB::table($subQuery)
            ->groupBy('customer_id')
            ->selectRaw('customer_id, customer_name, sum(totalPerPurchase) as total')
            ->orderBy('total', 'desc');

        // dd($subQuery);

        // statementで変数を設定できる
        // set @変数名 = 値 (mysqlの書き方) 
        // 3. 購入順に連番を振る 
        DB::statement('set @row_num = 0;');
        $subQuery = DB::table($subQuery)->selectRaw(
            '@row_num:= @row_num+1 as row_num, 
            customer_id, 
            customer_name,
            total'
        );

        // 4. 全体の件数を数え、1/10の値や合計金額を取得
        $count = DB::table($subQuery)->count();
        $total = DB::table($subQuery)->selectRaw('sum(total) as total')->get();
        $total = $total[0]->total; // 構成比用
        $decile = ceil($count / 10); // 10分の1の件数を変数に入れる
        $bindValues = [];
        $tempValue = 0;
        for ($i = 1; $i <= 10; $i++) {
            array_push($bindValues, 1 + $tempValue);
            $tempValue += $decile;
            array_push($bindValues, 1 + $tempValue);
        }

        DB::statement('set @row_num = 0;');
        $subQuery = DB::table($subQuery)->selectRaw("
            row_num, 
            customer_id, 
            customer_name,
            total,
            case
            when ? <= row_num and row_num < ? then 1
            when ? <= row_num and row_num < ? then 2
            when ? <= row_num and row_num < ? then 3
            when ? <= row_num and row_num < ? then 4
            when ? <= row_num and row_num < ? then 5
            when ? <= row_num and row_num < ? then 6
            when ? <= row_num and row_num < ? then 7
            when ? <= row_num and row_num < ? then 8
            when ? <= row_num and row_num < ? then 9
            when ? <= row_num and row_num < ? then 10
        end as decile
        ", $bindValues);
        // round, avg はmysqlの関数 
        // 6. グループ毎の合計・平均
        $subQuery = DB::table($subQuery)
            ->groupBy('decile')
            ->selectRaw(
                'decile,
                round(avg(total)) as average, 
                sum(total) as totalPerGroup'
            );

        // 7 構成比
        DB::statement("set @total = {$total} ;");
        $data = DB::table($subQuery)->selectRaw(
            'decile,
            average,
            totalPerGroup,
            round(100 * totalPerGroup / @total, 1) as totalRatio'
        )
            ->get();
    }
}
