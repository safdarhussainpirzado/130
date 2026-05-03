<?php

namespace App\Http\Controllers;

use Spatie\Activitylog\Models\Activity;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('system.audit_view');
        $query = Activity::with(['causer', 'subject'])
            ->orderBy('created_at', 'desc');

        // Filter by event type (created, updated, deleted)
        if ($request->filled('event')) {
            $query->where('event', $request->input('event'));
        }

        // Filter by subject type (model class name, case-insensitive partial match)
        if ($request->filled('subject_type')) {
            $query->where('subject_type', 'like', '%' . $request->input('subject_type') . '%');
        }

        // Filter by causer username
        if ($request->filled('causer')) {
            $query->whereHasMorph('causer', '*', function ($q) use ($request) {
                $q->where('username', 'like', '%' . $request->input('causer') . '%');
            });
        }

        // Filter by date range
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->input('to'));
        }

        $logs = $query->paginate(20)->withQueryString();

        return view('admin.audit.index', compact('logs'));
    }
}
