@php
    $notificationPayload = [
        'success' => session('success'),
        'status'  => session('status'),
        'error'   => session('error'),
        'failure' => session('failure'),
        'warning' => session('warning'),
        'info'    => session('info'),
        'errors'  => (isset($errors) && $errors instanceof \Illuminate\Support\ViewErrorBag && $errors->any()) ? $errors->all() : null,
    ];
@endphp
<script id="session-notifications-payload" type="application/json">
    {!! json_encode($notificationPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}
</script>
<script>
    (function() {
        const triggerNotifications = function() {
            try {
                const el = document.getElementById('session-notifications-payload');
                if (!el) return;
                const data = JSON.parse(el.textContent || '{}');
                if (data.success && window.showSuccess) window.showSuccess(data.success);
                if (data.status && window.showSuccess) window.showSuccess(data.status);
                if (data.error && window.showError) window.showError(data.error);
                if (data.failure && window.showError) window.showError(data.failure, "Failure");
                if (data.warning && window.showWarning) window.showWarning(data.warning);
                if (data.info && window.showInfo) window.showInfo(data.info);
                if (data.errors && window.showError) window.showError(data.errors, "Policy Violations");
            } catch (e) {
                console.error("Notifications parse error", e);
            }
        };

        if (window.showSuccess) {
            triggerNotifications();
        } else {
            document.addEventListener('DOMContentLoaded', triggerNotifications);
        }
    })();
</script>
