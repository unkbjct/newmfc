<?php

namespace App\Imports;

use App\Models\Load;
use App\Models\Report;
use App\Models\Report_column;
use App\Models\Report_value;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\ToCollection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\RemembersChunkOffset;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\BeforeImport;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ReportImport implements ToCollection,  WithEvents, WithChunkReading, WithBatchInserts
{
    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    use RemembersChunkOffset;

    public int $duplicate;
    public int $rows;
    public int $added;
    public string $status;

    public array $columns;
    public array $ruColumns;
    public array $newColumns;

    public string $fileName;

    public int $loadId;

    function __construct()
    {
        $this->duplicate = 0;
        $this->rows = 0;
        $this->added = 0;
        $this->status = 'processing';

        $this->columns = [
            'МФЦ, в котором зарегистрировано дело' => 'department',
            'Наименование услуги' => 'service_name',
            'Число услуг в деле' => 'services_count',
            'Дата регистрации' => 'registration_datetime',
            'Дата выдачи дела' => 'issue_datetime',
            'Наименование ОГВ, исполняющего услугу' => 'done_by',
            'Текущий статус услуги' => 'status',
        ];
        $this->ruColumns = [];

        $this->loadId = 0;
    }

    public function batchSize(): int
    {
        return 1000;
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function registerEvents(): array
    {
        return [
            BeforeImport::class => function (BeforeImport $event) {
                $totalRows = array_values($event->getReader()->getTotalRows());
                $load = Load::find($this->loadId);
                $load->rows = $this->rows = $totalRows[0] - 1;
                $load->save();
            },
            AfterImport::class => function () {
                $load = Load::find($this->loadId);
                $load->rows = $this->rows;
                $load->status = "loaded";
                $load->added = $this->added;
                $load->duplicates = $this->duplicate;
                $load->save();
            },
        ];
    }

    public function collection(Collection $rows)
    {
            foreach ($rows as $index => $row) {
                if ($index == 0 && $this->getChunkOffset() == 1) {
                    foreach ($row as $i => $title) {
                        if ($title === null) continue;
                        if (!array_key_exists($title, $this->columns)) {
                            $oCol = Report_column::where('title', '=', $title)->first();
                            if (!Schema::hasColumn('reports', Str::slug($title, '_')) && !$oCol) {
                                // Schema::table('reports', function ($table) use ($title) {
                                //     $table->string(Str::slug($title, '_'), 1000)->nullable();
                                // });
                                $nCol = new Report_column();
                                $nCol->title = $title;
                                $nCol->load_id = $this->loadId;
                                $nCol->save();
                                $this->ruColumns[$i] = [
                                    'id' => $nCol->id,
                                    'title' => $title,
                                ];
                            }else{
                                $this->ruColumns[$i] = [
                                    'id' => $oCol->id,
                                    'title' => $oCol->title,
                                ];
                            }
                        } else {
                            $this->ruColumns[$i] = $title;
                        }
                    }
                    continue;
                };

                $tmpReport = [];
                $tmpAdds = [];
                foreach ($row as $i => $value) {
                    if ($value === null) continue;
                    if(is_array($this->ruColumns[$i])){
                        array_push($tmpAdds, [
                            'report_column_id' => $this->ruColumns[$i]['id'],
                            'value' => $value,
                        ]);
                    }else {
                        $tmpReport[$this->columns[$this->ruColumns[$i]]] = $value;
                    }
                }
                $report = Report::where('load_id', '!=', $this->loadId)->firstOrNew($tmpReport);
                if($report->exists){
                    $this->duplicate++;
                }else {
                    $this->added++;
                    foreach($tmpReport as $key => $value){
                        $report->$key = $value;
                    }
                    $report->load_id = $this->loadId;
                    $report->save();
                    if(sizeof($tmpAdds)){
                        foreach($tmpAdds as $add){
                            if($add['value'] !== '') {
                                $reportValue = new Report_value();
                                $reportValue->report_id = $report->id;
                                $reportValue->report_column_id = $add['report_column_id'];
                                $reportValue->value = $add['value'];
                                $reportValue->save();
                            }
                        }
                    }
                }
            }   
            
            $load = Load::find($this->loadId);
            $load->added = $this->added;
            $load->duplicates = $this->duplicate;
            $load->save();
    }
}
