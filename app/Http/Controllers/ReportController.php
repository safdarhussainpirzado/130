<?php

namespace App\Http\Controllers;

use App\Exports\GenericReportExport;
use App\Exports\CustomFormattedExport;
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
        
        $availableColumns = ['type' => 'Category', 'sub_type' => 'Sub Type', 'total' => 'Total', 'percentage' => 'Efficiency %', 'emergency' => 'Priority P1'];
        $visibleColumns = $request->input('cols', $availableColumns);

        if ($request->has('export')) {
            $title = 'Call Type Summary Report';
            $subtitle = $this->getSubtitle($request);
            if ($request->export === 'excel') {
                return Excel::download(new CustomFormattedExport($title, $subtitle, array_values($visibleColumns), $data), 'call_type_summary.xlsx');
            }
            if ($request->export === 'pdf') {
                return Pdf::loadView('reports.pdf.call-type-summary', compact('data', 'visibleColumns'))->download('call_type_summary.pdf');
            }
        }

        return view('reports.call-type-summary', array_merge(
            compact('data', 'availableColumns', 'visibleColumns'),
            $this->getFilterData()
        ));
    }

    public function beatWise(Request $request)
    {
        $data = $this->reportService->beatWiseReport($request->all());
        $availableColumns = ['name' => 'Beat Name', 'total' => 'Total Calls', 'zone' => 'Parent Zone', 'sector' => 'Parent Sector'];
        $visibleColumns = $request->input('cols', $availableColumns);

        if ($request->has('export')) {
            if ($request->export === 'excel') {
                $title = 'Beat Wise Calls Summary Management Report';
                $subtitle = $this->getSubtitle($request);
                return Excel::download(new CustomFormattedExport($title, $subtitle, array_values($visibleColumns), $data), 'beat_wise_report.xlsx');
            }
        }

        return view('reports.beat-wise', array_merge(
            compact('data', 'availableColumns', 'visibleColumns'),
            $this->getFilterData()
        ));
    }

    public function agentWise(Request $request)
    {
        abort_if(!auth()->user()->hasPermission('reports.view'), 403, 'Unauthorized access to agent reports module');
        $data = $this->reportService->agentPerformance($request->all());
        
        $availableColumns = ['username' => 'Agent Identity', 'total' => 'Total Logs', 'avg_duration' => 'Avg Handle Time', 'efficiency' => 'Efficiency Matrix'];
        $visibleColumns = $request->input('cols', $availableColumns);

        if ($request->has('export')) {
            if ($request->export === 'excel') {
                $title = 'Agent Wise Calls Summary Management Report';
                $subtitle = $this->getSubtitle($request);
                return Excel::download(new CustomFormattedExport($title, $subtitle, array_values($visibleColumns), $data), 'agent_performance.xlsx');
            }
        }

        return view('reports.agent-wise', array_merge(
            compact('data', 'availableColumns', 'visibleColumns'),
            $this->getFilterData()
        ));
    }

    public function slaCompliance(Request $request)
    {
        $data = $this->reportService->slaCompliance($request->all());
        return view('reports.sla-compliance', array_merge(
            compact('data'),
            $this->getFilterData()
        ));
    }

    public function maxResponseTime(Request $request)
    {
        $data = $this->reportService->maxResponseTime($request->all());
        return view('reports.max-response-time', array_merge(
            compact('data'),
            $this->getFilterData()
        ));
    }

    public function predictiveAnalysis(Request $request)
    {
        $data = $this->reportService->predictiveAnalysis($request->all());
        return view('reports.predictive-analysis', array_merge(
            compact('data'),
            $this->getFilterData()
        ));
    }

    public function categoryAnalysis(Request $request)
    {
        $data = $this->reportService->categoryAnalysis($request->all());
        $availableColumns = ['category_label' => 'Major Category', 'total' => 'Total Volume', 'percentage' => 'Share %', 'avg_duration' => 'Avg Duration'];
        $visibleColumns = $request->input('cols', $availableColumns);

        return view('reports.category-analysis', array_merge(
            compact('data', 'availableColumns', 'visibleColumns'),
            $this->getFilterData()
        ));
    }

    public function junkCallsFrequency(Request $request)
    {
        $data = $this->reportService->junkCallsFrequency($request->all());
        $availableColumns = ['username' => 'Agent Name', 'junk_count' => 'Junk Calls Received', 'total_calls' => 'Total Calls Received', 'percentage' => 'Junk %'];
        $visibleColumns = $request->input('cols', $availableColumns);

        if ($request->has('export')) {
            if ($request->export === 'excel') {
                $title = 'Junk Calls Frequency Report';
                $subtitle = $this->getSubtitle($request);
                return Excel::download(new CustomFormattedExport($title, $subtitle, array_values($visibleColumns), $data), 'junk_calls_frequency.xlsx');
            }
        }

        return view('reports.junk-calls', array_merge(
            compact('data', 'availableColumns', 'visibleColumns'),
            $this->getFilterData()
        ));
    }

    private function getFilterData(): array
    {
        return [
            'offices' => [
                'zones' => \App\Models\Office::zones()->active()->get(['id', 'name']),
                'sectors' => \App\Models\Office::sectors()->active()->get(['id', 'name', 'parent_id']),
                'beats' => \App\Models\Office::beats()->active()->get(['id', 'name', 'parent_id']),
            ],
            'agents' => \App\Models\User::whereHas('role', function($q) {
                $q->where('name', 'agent');
            })->get(['id', 'username']),
            'callTypes' => \App\Models\CallType::active()->get(['id', 'name', 'category']),
        ];
    }

    private function getSubtitle(Request $request): string
    {
        $dateFrom = $request->input('date_from', now()->toDateString());
        $dateTo = $request->input('date_to', now()->toDateString());
        $timeFrom = $request->input('time_from', '00:00');
        $timeTo = $request->input('time_to', '23:59');

        $dF = \Carbon\Carbon::parse($dateFrom)->format('d-M-Y');
        $dT = \Carbon\Carbon::parse($dateTo)->format('d-M-Y');
        
        $tF = str_replace(':', '', $timeFrom) . ' hrs';
        $tT = str_replace(':', '', $timeTo) . ' hrs';

        return "{$dF} to {$dT} ({$tF} to {$tT})";
    }
}


