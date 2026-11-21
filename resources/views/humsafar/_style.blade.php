@once
<style>
    .hs-wrap{display:grid;gap:18px}
    .hs-head{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px}
    .hs-head h1{margin:0;font-size:20px;font-weight:900;color:#0f172a;letter-spacing:-.01em}
    .hs-head p{margin:2px 0 0;font-size:12px;font-weight:600;color:#64748b}
    .hs-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px}
    .hs-card{background:#fff;border:1px solid #e8edf4;border-radius:18px;padding:14px 16px;box-shadow:0 8px 24px -16px rgba(15,23,42,.25)}
    .hs-card b{display:block;font-size:24px;font-weight:900;color:#0f172a}
    .hs-card span{font-size:10px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#94a3b8}
    .hs-panel{background:#fff;border:1px solid #e8edf4;border-radius:20px;overflow:hidden;box-shadow:0 10px 30px -20px rgba(15,23,42,.3)}
    .hs-table{width:100%;border-collapse:collapse;font-size:12.5px}
    .hs-table th{padding:11px 14px;text-align:left;font-size:10px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#64748b;background:#f8fafc;border-bottom:1px solid #eef1f6;white-space:nowrap}
    .hs-table td{padding:11px 14px;border-bottom:1px solid #f1f5f9;color:#334155;font-weight:600;vertical-align:middle}
    .hs-table tr:last-child td{border-bottom:0}
    .hs-pill{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:999px;font-size:10px;font-weight:800;letter-spacing:.05em;text-transform:uppercase;background:#f1f5f9;color:#475569}
    .hs-pill.ok{background:#dcfce7;color:#15803d}.hs-pill.warn{background:#fef3c7;color:#b45309}.hs-pill.bad{background:#ffe4e6;color:#be123c}.hs-pill.info{background:#dbeafe;color:#1d4ed8}
    .hs-btn{appearance:none;border:0;cursor:pointer;border-radius:12px;padding:8px 14px;font-size:11px;font-weight:800;letter-spacing:.05em;text-transform:uppercase;background:linear-gradient(135deg,#2563eb,#4f46e5);color:#fff;font-family:inherit}
    .hs-btn.ghost{background:#f1f5f9;color:#334155}.hs-btn.green{background:linear-gradient(135deg,#059669,#10b981)}.hs-btn.red{background:linear-gradient(135deg,#e11d48,#f43f5e)}
    .hs-input{height:40px;border:1px solid #e2e8f0;border-radius:12px;padding:0 12px;font-size:12.5px;font-weight:700;background:#fff;color:#1e293b;font-family:inherit;min-width:0}
    .hs-empty{padding:40px;text-align:center;color:#94a3b8;font-weight:700;font-size:13px}
    .hs-flash{padding:10px 14px;border-radius:12px;font-size:12px;font-weight:700}
    .hs-flash.ok{background:#dcfce7;color:#15803d}.hs-flash.err{background:#ffe4e6;color:#be123c}
    .hs-row{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
    .hs-scroll{overflow-x:auto}
    .hs-modalbg{position:fixed;inset:0;z-index:9100;background:rgba(15,23,42,.35);display:flex;align-items:center;justify-content:center}
    .hs-modal{background:#fff;border-radius:22px;width:min(440px,92vw);padding:22px;box-shadow:0 30px 70px rgba(15,23,42,.35)}
</style>
@endonce
@if(session('success'))<div class="hs-flash ok">{{ session('success') }}</div>@endif
@if($errors->any())<div class="hs-flash err">{{ $errors->first() }}</div>@endif
