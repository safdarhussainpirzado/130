<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('system.audit_view');
        
        $query = AuditLog::with('user')
            ->orderBy('created_at', 'desc');

        // Filter by action (equivalent to event)
        if ($request->filled('event')) {
            $query->where('action', $request->input('event'));
        }

        // Filter by table (equivalent to subject_type)
        if ($request->filled('subject_type')) {
            $query->where('table_name', 'like', '%' . $request->input('subject_type') . '%');
        }

        // Filter by causer username
        if ($request->filled('causer')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('username', 'like', '%' . $request->input('causer') . '%');
            });
        }

        // Filter by date range
        if ($request->filled('from')) {
            $query->where('created_at', '>=', \Carbon\Carbon::parse($request->input('from'))->startOfDay());
        }
        if ($request->filled('to')) {
            $query->where('created_at', '<=', \Carbon\Carbon::parse($request->input('to'))->endOfDay());
        }

        $logs = $query->paginate(20)->withQueryString();

        return view('admin.audit.index', compact('logs'));
    }
}
