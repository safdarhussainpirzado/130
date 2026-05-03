<?php

namespace App\Http\Controllers;

use App\Exports\GenericReportExport;
use App\Services\ReportService;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    use AuthorizesRequests;

    protected $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    public function index()
    {
        abort_if(!auth()->user()->hasPermission('reports.view'), 403, 'Unauthorized access to reports module');

        $dailyCalls = DB::table('calls')
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as total'))
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->limit(30)
            ->get();
            
        $typeDistribution = DB::table('calls')
            ->join('call_types', 'calls.call_type_id', '=', 'call_types.id')
            ->select('call_types.name', DB::raw('count(*) as total'))
            ->groupBy('call_types.name')
            ->get();

        return view('reports.index', compact('dailyCalls', 'typeDistribution'));
    }

    public function callTypeSummary(Request $request)
    {
        $data = $this->reportService->callTypeSummary($request->all());

        if ($request->has('export')) {
            if ($request->export === 'excel') {
                return Excel::download(new GenericReportExport($data, ['Type', 'Sub Type', 'Total', 'Percentage', 'Emergency']), 'call_type_summary.xlsx');
            }
            if ($request->export === 'pdf') {
                return Pdf::loadView('reports.pdf.call-type-summary', compact('data'))->download('call_type_summary.pdf');
            }
        }

        return view('reports.call-type-summary', compact('data'));
    }

    public function beatWise(Request $request)
    {
        $data = $this->reportService->beatWiseReport($request->all());

        if ($request->has('export')) {
            if ($request->export === 'excel') {
                return Excel::download(new GenericReportExport($data, ['Beat Name', 'Total Calls']), 'beat_wise_report.xlsx');
            }
        }

        return view('reports.beat-wise', compact('data'));
    }

    public function agentWise(Request $request)
    {
        abort_if(!auth()->user()->hasPermission('reports.view'), 403, 'Unauthorized access to agent reports module');
        $data = $this->reportService->agentPerformance($request->all());

        if ($request->has('export')) {
            if ($request->export === 'excel') {
                return Excel::download(new GenericReportExport($data, ['Username', 'Total Calls', 'Avg Duration']), 'agent_performance.xlsx');
            }
        }

        return view('reports.agent-wise', compact('data'));
    }

    public function slaCompliance(Request $request)
    {
        $data = $this->reportService->slaCompliance($request->all());
        return view('reports.sla-compliance', compact('data'));
    }

    public function maxResponseTime(Request $request)
    {
        $data = $this->reportService->maxResponseTime($request->all());
        return view('reports.max-response-time', compact('data'));
    }

    public function predictiveAnalysis(Request $request)
    {
        $data = $this->reportService->predictiveAnalysis($request->all());
        return view('reports.predictive-analysis', compact('data'));
    }
}


