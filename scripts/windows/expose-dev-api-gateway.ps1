# Run in an ELEVATED PowerShell on the Windows host. Forwards TCP 8480 -> WSL2 dev API gateway and opens
# the Windows firewall for the LOCAL SUBNET and Tailscale (100.64.0.0/10) only. Never the open internet.
# Remove everything:  .\expose-dev-api-gateway.ps1 -Remove
param([switch]$Remove)
$port = 8480
$rule = 'NHMP Dev API Gateway 8480'
$id = [Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()
if (-not $id.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) { Write-Error 'Run as Administrator'; exit 1 }
& "$env:SystemRoot\System32\netsh.exe" interface portproxy delete v4tov4 listenport=$port listenaddress=0.0.0.0 2>$null | Out-Null
Remove-NetFirewallRule -DisplayName $rule -ErrorAction SilentlyContinue
if ($Remove) { Write-Host 'Removed port forward and firewall rule.'; exit 0 }
$wsl = @(((& "$env:SystemRoot\System32\wsl.exe" hostname -I) -split "\s+") | Where-Object { $_ })[0]
if (-not $wsl) { Write-Error 'Could not read the WSL IP (is WSL running?)'; exit 1 }
& "$env:SystemRoot\System32\netsh.exe" interface portproxy add v4tov4 listenport=$port listenaddress=0.0.0.0 connectport=$port connectaddress=$wsl | Out-Null
New-NetFirewallRule -DisplayName $rule -Direction Inbound -Protocol TCP -LocalPort $port -Action Allow -Profile Any -RemoteAddress 'LocalSubnet','100.64.0.0/10' | Out-Null
Write-Host "Forwarding 0.0.0.0:$port -> ${wsl}:$port (WSL IP changes after a WSL restart: re-run this script)."
Get-NetIPAddress -AddressFamily IPv4 | Where-Object { $_.IPAddress -match '^(192\.168|10\.|172\.(1[6-9]|2\d|3[01])|100\.)' -and $_.InterfaceAlias -notmatch 'vEthernet|WSL|Loopback' } |
  ForEach-Object { Write-Host ("Share:  http://{0}:{1}/api/v1/travel-advisory/routes   ({2})" -f $_.IPAddress, $port, $_.InterfaceAlias) }
