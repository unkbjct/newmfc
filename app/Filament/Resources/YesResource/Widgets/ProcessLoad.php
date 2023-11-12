<?php

namespace App\Filament\Resources\YResource\Widgets;

use App\Models\Load;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Actions\ViewAction;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use App\Tables\Columns\LoadColumn; 
use Illuminate\Support\Facades\DB;

class ProcessLoad extends BaseWidget
{

    // protected static ?string $pollingInterval = '2s';

    public static function canView(): bool
    {
        $load = Load::latest()->first();
        return ($load && $load->status == "processing") ? true : false;
    }

    protected function getStats(): array
    {
        $jobs = DB::table('jobs')->get();
        $check = $jobs->isNotEmpty();
        $load = Load::latest()->first();
        $load->rows = $load->rows ? $load->rows : 100;
        $color = $check ? "warning" : (($load->status == 'crash') ? 'danger' : 'success');
        
        return [
            Stat::make(round(($load->added + $load->duplicates) / ($load->rows / 100), 2) . "%", ($check) ? LoadColumn::make('') : (($load->status == 'crash') ? 'Ошибка' : 'Загрузка завершена'))
                ->description(($check) ? 'Данные импортируются': (($load->status == 'crash') ? 'Данные загрузились не полностью, повторите попытку' : 'Данные загрузились успешно'))
                ->color($color),
            Stat::make('', $load->rows)
                ->description('Всего записей в импорте')
                ->color($color),
            Stat::make('', $load->added + $load->duplicates)
                ->description('Обработано записей в импорте')
                ->color($color),

        ];
    }
}
