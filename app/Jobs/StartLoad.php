<?php

namespace App\Jobs;

use App\Imports\ReportImport;
use App\Models\Load;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Queue\Events\JobFailed;
use Throwable;
use Illuminate\Support\Facades\Log;

class StartLoad implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    /**
     * Create a new job instance.
     */

    public function __construct(public $data)
    {
        $this->data = $data;
    }

    public $timeout = 60 * 60; // one hour
    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $import = new ReportImport;
        $import->fileName = $this->data['filename'];
        $import->loadId = $this->data['loadId'];
        Excel::import($import, $this->data['filename'], 'public');
    }

    public function failed(Throwable $error){
        $load = Load::find($this->data['loadId']);
        $load->status = 'crash';
        // $load->added = $this->tmpImport->added;
        $load->save();
        Log::debug("hello, job falied!");
    }
}
