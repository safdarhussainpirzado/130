const http = require('http');
const fs = require('fs');
const path = require('path');
const url = require('url');

const PORT = process.env.TEST_PORT || 8888;
const BASE_DIR = path.resolve(__dirname, '../../');

// Mock data: 481 Call Center users (450 agents + 31 supervisors) + 110 field staff
function generateMockUsers() {
  const users = [];
  let id = 1;

  // 10 HQ Administrators
  for (let i = 1; i <= 5; i++) {
    users.push({
      id: id++,
      uuid: `uuid-admin-${i}`,
      username: `super_admin_${i}`,
      full_name: `Super Admin Officer ${i}`,
      email: `super_admin_${i}@nhmp.gov.pk`,
      mobile_no: `0300000000${i}`,
      cnic: `37405-000000${i}-1`,
      is_active: true,
      role_id: 1,
      role_name: 'super_admin',
      role: { id: 1, name: 'super_admin', display_name: 'Super Administrator' },
      scope_unit_type: 'National',
      scope_unit_type_raw: 'national',
      scope_unit_name: 'National Headquarter',
      designation: { id: 1, name: 'Inspector General', bps: 21 },
    });
  }
  for (let i = 1; i <= 5; i++) {
    users.push({
      id: id++,
      uuid: `uuid-opadmin-${i}`,
      username: `op_admin_${i}`,
      full_name: `Operations Manager ${i}`,
      email: `op_admin_${i}@nhmp.gov.pk`,
      mobile_no: `0300000001${i}`,
      cnic: `37405-000001${i}-1`,
      is_active: true,
      role_id: 2,
      role_name: 'operation_admin',
      role: { id: 2, name: 'operation_admin', display_name: 'Operation Admin' },
      scope_unit_type: 'National',
      scope_unit_type_raw: 'national',
      scope_unit_name: 'Operations HQ',
      designation: { id: 2, name: 'Deputy Inspector General', bps: 20 },
    });
  }

  // 31 Call Center Supervisors
  for (let i = 1; i <= 31; i++) {
    users.push({
      id: id++,
      uuid: `uuid-sup-${i}`,
      username: `supervisor_${String(i).padStart(2, '0')}`,
      full_name: `Helpline Supervisor ${i}`,
      email: `sup_${i}@nhmp.gov.pk`,
      mobile_no: `030010000${String(i).padStart(2, '0')}`,
      cnic: `37405-10000${String(i).padStart(2, '0')}-1`,
      is_active: i !== 31, // 1 inactive for filter test
      role_id: 4,
      role_name: 'agent_supervisor',
      role: { id: 4, name: 'agent_supervisor', display_name: 'Agent Supervisor' },
      scope_unit_type: 'Call Center',
      scope_unit_type_raw: 'call_centre',
      scope_unit_name: 'Helpline 130',
      designation: { id: 5, name: 'Inspector', bps: 16 },
    });
  }

  // 450 Call Center Agents (Total Call Center staff = 31 + 450 = 481)
  for (let i = 1; i <= 450; i++) {
    const isInactive = (i % 50 === 0); // 9 inactive agents
    users.push({
      id: id++,
      uuid: `uuid-agent-${i}`,
      username: `agent_${String(i).padStart(3, '0')}`,
      full_name: `Call Center Officer ${i}`,
      email: `agent_${i}@nhmp.gov.pk`,
      mobile_no: `0300200${String(i).padStart(4, '0')}`,
      cnic: `37405-200${String(i).padStart(4, '0')}-1`,
      is_active: !isInactive,
      role_id: 5,
      role_name: 'agent',
      role: { id: 5, name: 'agent', display_name: 'Call Center Agent' },
      scope_unit_type: 'Call Center',
      scope_unit_type_raw: 'call_centre',
      scope_unit_name: 'Helpline 130',
      designation: { id: 8, name: 'Sub-Inspector', bps: 14 },
    });
  }

  // 20 Zone Users
  for (let i = 1; i <= 20; i++) {
    users.push({
      id: id++,
      uuid: `uuid-zone-${i}`,
      username: `zone_officer_${i}`,
      full_name: `Zone Commander ${i}`,
      email: `zone_${i}@nhmp.gov.pk`,
      mobile_no: `030030000${String(i).padStart(2, '0')}`,
      cnic: `37405-30000${String(i).padStart(2, '0')}-1`,
      is_active: true,
      role_id: 6,
      role_name: 'zone_admin',
      role: { id: 6, name: 'zone_admin', display_name: 'Zone Administrator' },
      scope_unit_type: 'Zone',
      scope_unit_type_raw: 'zone',
      scope_unit_name: `Zone ${i}`,
      scope_unit_id: i,
      designation: { id: 3, name: 'Senior Superintendent of Police', bps: 19 },
    });
  }

  // 30 Sector Users
  for (let i = 1; i <= 30; i++) {
    const parentZoneId = Math.ceil(i / 3);
    users.push({
      id: id++,
      uuid: `uuid-sector-${i}`,
      username: `sector_officer_${i}`,
      full_name: `Sector Commander ${i}`,
      email: `sector_${i}@nhmp.gov.pk`,
      mobile_no: `030040000${String(i).padStart(2, '0')}`,
      cnic: `37405-40000${String(i).padStart(2, '0')}-1`,
      is_active: true,
      role_id: 7,
      role_name: 'sector_admin',
      role: { id: 7, name: 'sector_admin', display_name: 'Sector Administrator' },
      scope_unit_type: 'Sector',
      scope_unit_type_raw: 'sector',
      scope_unit_name: `Sector M-${i}`,
      scope_unit_id: i,
      parent_zone_id: parentZoneId,
      designation: { id: 4, name: 'Superintendent of Police', bps: 18 },
    });
  }

  // 50 Beat Operators
  for (let i = 1; i <= 50; i++) {
    const parentSectorId = Math.ceil(i / 2);
    const parentZoneId = Math.ceil(parentSectorId / 3);
    users.push({
      id: id++,
      uuid: `uuid-beat-${i}`,
      username: `beat_op_${i}`,
      full_name: `Patrol Officer ${i}`,
      email: `beat_${i}@nhmp.gov.pk`,
      mobile_no: `030050000${String(i).padStart(2, '0')}`,
      cnic: `37405-50000${String(i).padStart(2, '0')}-1`,
      is_active: true,
      role_id: 8,
      role_name: 'beat_operator',
      role: { id: 8, name: 'beat_operator', display_name: 'Wireless Operator' },
      scope_unit_type: 'Beat',
      scope_unit_type_raw: 'beat',
      scope_unit_name: `Beat ${i}`,
      scope_unit_id: i,
      parent_sector_id: parentSectorId,
      parent_zone_id: parentZoneId,
      designation: { id: 10, name: 'Head Constable', bps: 9 },
    });
  }

  return users;
}

const allMockUsers = generateMockUsers();

// Mock calls: 50 calls
function generateMockCalls() {
  const calls = [];
  const statuses = ['pending', 'in_progress', 'tiger_reached', 'completed'];
  for (let i = 1; i <= 50; i++) {
    const callNum = `CRM-2026-${String(i).padStart(5, '0')}`;
    const status = statuses[i % statuses.length];
    calls.push({
      id: i,
      call_number: callNum,
      caller_name: `Citizen Traveler ${i}`,
      caller_number: `0300123${String(i).padStart(4, '0')}`,
      status: status,
      priority: (i % 3) + 1,
      office_id: (i % 10) + 1,
      office: { id: (i % 10) + 1, name: `Beat ${(i % 10) + 1}`, type: 'beat' },
      agent_id: 11 + (i % 30),
      agent: { id: 11 + (i % 30), username: `agent_${String(i % 30).padStart(3, '0')}`, full_name: `Agent ${i}` },
      call_type: { id: 1, name: 'Emergency Assistance' },
      call_sub_type: { id: 2, name: 'Mechanical Breakdown' },
      carriageway: { id: 1, road_name: 'M-2 Motorway', road_short: 'M-2' },
      location: `KM ${100 + i}`,
      created_at: new Date(Date.now() - i * 3600000).toISOString(),
    });
  }
  return calls;
}

const allMockCalls = generateMockCalls();

// Render User Management Page
function renderUsersPage() {
  const usersJson = JSON.stringify(allMockUsers).replace(/</g, '\\u003c');
  const rolesJson = JSON.stringify([
    { id: 1, name: 'super_admin', display_name: 'Super Administrator', scope_level: 'national' },
    { id: 2, name: 'operation_admin', display_name: 'Operation Admin', scope_level: 'national' },
    { id: 4, name: 'agent_supervisor', display_name: 'Agent Supervisor', scope_level: 'call_centre' },
    { id: 5, name: 'agent', display_name: 'Call Agent', scope_level: 'call_centre' },
    { id: 6, name: 'zone_admin', display_name: 'Zone Administrator', scope_level: 'zone' },
    { id: 7, name: 'sector_admin', display_name: 'Sector Administrator', scope_level: 'sector' },
    { id: 8, name: 'beat_operator', display_name: 'Wireless Operator', scope_level: 'beat' },
  ]).replace(/</g, '\\u003c');

  return `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>User Management - NHMP 130 Helpline</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/node_modules/@fortawesome/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="/build/assets/app-BXZ6LzFI.css">
  <script src="/node_modules/alpinejs/dist/cdn.min.js" defer></script>
  <style>
    body { font-family: system-ui, -apple-system, sans-serif; background: #f8fafc; color: #1e293b; margin: 0; padding: 20px; }
    .card-3d-active { transform: translate(2px, -2px) !important; border: 2px solid #059669 !important; }
    .card-3d-active.emerald { box-shadow: -4px 4px 0px #047857, 0 10px 15px -3px rgba(16, 185, 129, 0.4) !important; }
    .active-row { background-color: #f0fdf4; }
  </style>
</head>
<body>
  <!-- Embedded Initial Data -->
  <script id="users-index-data" type="application/json">
  {
    "items": ${usersJson},
    "roles": ${rolesJson},
    "designations": [{"id": 5, "name": "Inspector", "bps": 16}, {"id": 8, "name": "Sub-Inspector", "bps": 14}],
    "zones": [{"id": 1, "name": "Zone 1"}, {"id": 2, "name": "Zone 2"}],
    "sectors": [{"id": 1, "name": "Sector M-1", "zone_id": 1}],
    "beats": [{"id": 1, "name": "Beat 1", "sector_id": 1}],
    "currentUserRole": "super_admin",
    "currentUserId": 1,
    "pendingRequests": []
  }
  </script>

  <script>
    function userManager(config) {
      return {
        items: config.items || [],
        roles: config.roles || [],
        search: '',
        filterRole: '',
        filterStatus: '',
        filterZone: '',
        filterSector: '',
        filterBeat: '',
        filterCallCentre: '',
        page: 1,
        perPage: 25,
        sortBy: 'id',
        sortDirection: 'asc',
        selectedUser: null,
        showViewModal: false,
        showEditModal: false,
        showScopeModal: false,
        showPasswordModal: false,
        showSidebar: false,

        get filteredItems() {
          return this.items.filter(item => {
            const searchLower = this.search.toLowerCase();
            const matchesSearch = (item.username || '').toLowerCase().includes(searchLower) ||
                                 (item.full_name || '').toLowerCase().includes(searchLower) ||
                                 (item.cnic || '').toLowerCase().includes(searchLower);
            const matchesRole = !this.filterRole || item.role_id == this.filterRole;
            let matchesStatus = true;

            if (this.filterStatus === 'active') {
              matchesStatus = item.is_active;
            } else if (this.filterStatus === 'inactive') {
              matchesStatus = !item.is_active;
            } else if (this.filterStatus === 'admin') {
              matchesStatus = item.role_name === 'operation_admin' || item.role_name === 'super_admin';
            } else if (this.filterStatus === 'zone_user') {
              matchesStatus = item.scope_unit_type_raw === 'zone';
            } else if (this.filterStatus === 'sector_user') {
              matchesStatus = item.scope_unit_type_raw === 'sector';
            } else if (this.filterStatus === 'beat_user') {
              matchesStatus = item.scope_unit_type_raw === 'beat';
            } else if (this.filterStatus === 'call_centre_user') {
              matchesStatus = item.scope_unit_type_raw === 'call_centre'
                || item.scope_unit_type_raw === 'call_center'
                || item.role_name === 'agent'
                || item.role_name === 'agent_supervisor'
                || (item.role && ['agent', 'agent_supervisor'].includes(item.role.name));
            }

            let matchesArea = true;
            if (this.filterCallCentre && (item.scope_unit_type_raw === 'call_centre' || item.scope_unit_type_raw === 'call_center' || item.role_name === 'agent' || item.role_name === 'agent_supervisor')) {
              matchesArea = true;
            }

            return matchesSearch && matchesRole && matchesStatus && matchesArea;
          }).sort((a, b) => {
            let fieldA = (a[this.sortBy] || '').toString().toLowerCase();
            let fieldB = (b[this.sortBy] || '').toString().toLowerCase();
            if (fieldA < fieldB) return this.sortDirection === 'asc' ? -1 : 1;
            if (fieldA > fieldB) return this.sortDirection === 'asc' ? 1 : -1;
            return 0;
          });
        },

        get totalPages() {
          return Math.ceil(this.filteredItems.length / this.perPage) || 1;
        },

        get pagedItems() {
          if (this.page > this.totalPages) this.page = Math.max(1, this.totalPages);
          const start = (this.page - 1) * this.perPage;
          return this.filteredItems.slice(start, start + this.perPage);
        },

        viewItem(user) {
          this.selectedUser = user;
          this.showViewModal = true;
        },

        openScopeModal(user) {
          this.selectedUser = user;
          this.showScopeModal = true;
        },

        toggleSort(field) {
          if (this.sortBy === field) {
            this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
          } else {
            this.sortBy = field;
            this.sortDirection = 'asc';
          }
        }
      };
    }
  </script>

  <div x-data="userManager(JSON.parse(document.getElementById('users-index-data').textContent))" id="user-management-app">
    
    <!-- STATS CARDS -->
    <div class="grid grid-cols-2 md:grid-cols-6 gap-4 mb-6">
      <!-- Call Center Card -->
      <div id="callCenterCard" 
           @click="filterStatus = (filterStatus === 'call_centre_user' ? '' : 'call_centre_user')" 
           :class="filterStatus === 'call_centre_user' ? 'card-3d-active emerald' : ''" 
           class="p-5 bg-white rounded-2xl shadow-sm border border-slate-200 cursor-pointer hover:border-emerald-500 transition-all">
        <p class="text-xs font-bold text-emerald-600 uppercase">Call Center</p>
        <h4 id="callCenterCount" class="text-3xl font-extrabold text-slate-800 mt-1" 
            x-text="items.filter(i => i.scope_unit_type_raw === 'call_centre' || i.scope_unit_type_raw === 'call_center' || i.role_name === 'agent' || i.role_name === 'agent_supervisor').length"></h4>
      </div>

      <!-- Inactive Card -->
      <div id="inactiveCard"
           @click="filterStatus = (filterStatus === 'inactive' ? '' : 'inactive')"
           :class="filterStatus === 'inactive' ? 'card-3d-active' : ''"
           class="p-5 bg-white rounded-2xl shadow-sm border border-slate-200 cursor-pointer hover:border-rose-500 transition-all">
        <p class="text-xs font-bold text-rose-600 uppercase">Inactive</p>
        <h4 id="inactiveCount" class="text-3xl font-extrabold text-slate-800 mt-1"
            x-text="items.filter(i => !i.is_active).length"></h4>
      </div>

      <!-- Total Users Card -->
      <div class="p-5 bg-white rounded-2xl shadow-sm border border-slate-200">
        <p class="text-xs font-bold text-blue-600 uppercase">Total Personnel</p>
        <h4 id="totalUsersCount" class="text-3xl font-extrabold text-slate-800 mt-1" x-text="items.length"></h4>
      </div>
    </div>

    <!-- MAIN PANEL -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
      <div class="flex flex-col md:flex-row justify-between items-center gap-4 mb-6">
        <div>
          <h2 class="text-2xl font-black text-slate-900">
            User Management <span id="filteredCountText" class="text-base text-slate-500 font-normal" x-text="'(' + filteredItems.length + ' users)'"></span>
          </h2>
          <p class="text-xs text-slate-500">Manage personnel identities, helpline roles, and operational scopes</p>
        </div>

        <!-- Controls / Search / Filter -->
        <div class="flex items-center gap-3 w-full md:w-auto">
          <input type="text" 
                 id="userSearchInput" 
                 x-model="search" 
                 placeholder="Search users by name, username, CNIC..." 
                 class="px-4 py-2 border border-slate-300 rounded-lg text-sm w-full md:w-64 focus:outline-none focus:ring-2 focus:ring-emerald-500">

          <select id="roleFilterSelect" x-model="filterRole" class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
            <option value="">All Roles</option>
            <option value="4">Agent Supervisor</option>
            <option value="5">Call Agent</option>
            <option value="1">Super Admin</option>
          </select>

          <select id="perPageSelect" x-model.number="perPage" class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
            <option value="10">10 / page</option>
            <option value="25">25 / page</option>
            <option value="50">50 / page</option>
          </select>
        </div>
      </div>

      <!-- TABLE -->
      <div class="overflow-x-auto border border-slate-200 rounded-xl">
        <table class="w-full text-left text-sm" id="usersTable">
          <thead class="bg-slate-50 text-slate-700 font-bold border-b border-slate-200">
            <tr>
              <th class="p-3 cursor-pointer" @click="toggleSort('id')">ID</th>
              <th class="p-3 cursor-pointer" @click="toggleSort('username')">Username</th>
              <th class="p-3 cursor-pointer" @click="toggleSort('full_name')">Full Name</th>
              <th class="p-3">Role</th>
              <th class="p-3">Scope / Unit</th>
              <th class="p-3">Status</th>
              <th class="p-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <template x-for="item in pagedItems" :key="item.id">
              <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors user-row">
                <td class="p-3 font-mono text-xs text-slate-500" x-text="item.id"></td>
                <td class="p-3 font-semibold text-slate-800" x-text="item.username"></td>
                <td class="p-3 text-slate-700" x-text="item.full_name"></td>
                <td class="p-3">
                  <span class="px-2 py-0.5 rounded-full text-xs font-semibold"
                        :class="item.role_name === 'agent_supervisor' ? 'bg-purple-100 text-purple-700' : (item.role_name === 'agent' ? 'bg-emerald-100 text-emerald-700' : 'bg-blue-100 text-blue-700')"
                        x-text="item.role?.display_name || item.role_name"></span>
                </td>
                <td class="p-3">
                  <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700" 
                        x-text="item.scope_unit_type + ' (' + item.scope_unit_name + ')'"></span>
                </td>
                <td class="p-3">
                  <span class="px-2 py-0.5 rounded-full text-xs font-semibold"
                        :class="item.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                        x-text="item.is_active ? 'Active' : 'Inactive'"></span>
                </td>
                <td class="p-3 text-right space-x-1">
                  <button @click="viewItem(item)" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 rounded text-xs font-semibold view-btn">View</button>
                  <button @click="openScopeModal(item)" class="px-2 py-1 bg-blue-50 hover:bg-blue-100 text-blue-700 rounded text-xs font-semibold scope-btn">Scopes</button>
                </td>
              </tr>
            </template>
            <tr x-show="filteredItems.length === 0" id="emptyRow">
              <td colspan="7" class="p-8 text-center text-slate-500">
                <i class="fas fa-user-slash text-2xl mb-2 text-slate-300"></i>
                <p>No users found matching the selected filter criteria.</p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- PAGINATION -->
      <div class="flex justify-between items-center mt-4 text-xs text-slate-600" id="paginationControls">
        <div>
          Showing <span class="font-bold" x-text="filteredItems.length === 0 ? 0 : (page - 1) * perPage + 1"></span>
          to <span class="font-bold" x-text="Math.min(page * perPage, filteredItems.length)"></span>
          of <span class="font-bold" x-text="filteredItems.length"></span> entries
        </div>
        <div class="flex gap-1" id="paginationButtons">
          <button @click="page--" :disabled="page <= 1" class="px-3 py-1 border rounded bg-white hover:bg-slate-50 disabled:opacity-50" id="prevPageBtn">Previous</button>
          <span class="px-3 py-1 font-bold text-slate-800" id="pageIndicator" x-text="'Page ' + page + ' of ' + totalPages"></span>
          <button @click="page++" :disabled="page >= totalPages" class="px-3 py-1 border rounded bg-white hover:bg-slate-50 disabled:opacity-50" id="nextPageBtn">Next</button>
        </div>
      </div>
    </div>

    <!-- VIEW USER MODAL -->
    <div x-show="showViewModal" id="viewUserModal" class="fixed inset-0 bg-slate-900/50 flex items-center justify-center p-4 z-50" style="display: none;">
      <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl" @click.away="showViewModal = false">
        <h3 class="text-lg font-bold text-slate-800 mb-4">User Details</h3>
        <template x-if="selectedUser">
          <div class="space-y-2 text-sm text-slate-700" id="modalUserDetails">
            <p><strong>Username:</strong> <span x-text="selectedUser.username"></span></p>
            <p><strong>Name:</strong> <span x-text="selectedUser.full_name"></span></p>
            <p><strong>CNIC:</strong> <span x-text="selectedUser.cnic"></span></p>
            <p><strong>Role:</strong> <span x-text="selectedUser.role?.display_name || selectedUser.role_name"></span></p>
            <p><strong>Scope Type:</strong> <span x-text="selectedUser.scope_unit_type"></span></p>
            <p><strong>Scope Raw:</strong> <span x-text="selectedUser.scope_unit_type_raw"></span></p>
            <p><strong>Scope Unit:</strong> <span x-text="selectedUser.scope_unit_name"></span></p>
          </div>
        </template>
        <button @click="showViewModal = false" class="mt-6 w-full py-2 bg-slate-800 hover:bg-slate-900 text-white rounded-lg font-bold text-sm" id="closeModalBtn">Close</button>
      </div>
    </div>

  </div>
</body>
</html>`;
}

// Render Calls Directory Page
function renderCallsPage(query) {
  const callsJson = JSON.stringify(allMockCalls).replace(/</g, '\\u003c');
  return `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Helpline Calls - NHMP 130</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/node_modules/@fortawesome/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="/build/assets/app-BXZ6LzFI.css">
  <script src="/node_modules/alpinejs/dist/cdn.min.js" defer></script>
  <style>
    body { font-family: system-ui, -apple-system, sans-serif; background: #f8fafc; color: #1e293b; margin: 0; padding: 20px; }
  </style>
</head>
<body>
  <script id="calls-index-data" type="application/json">
  {
    "items": ${callsJson},
    "per_page": 10,
    "current_page": 1,
    "last_page": 5,
    "total": 50,
    "stats": {"total": 50, "pending": 12, "in_progress": 13, "tiger_reached": 12, "completed": 13},
    "zones": [{"id": 1, "name": "Zone 1"}],
    "sectors": [{"id": 1, "name": "Sector M-1"}],
    "beats": [{"id": 1, "name": "Beat 1"}],
    "callTypes": [{"id": 1, "name": "Emergency Assistance"}],
    "callSubTypes": [{"id": 2, "name": "Mechanical Breakdown"}],
    "agents": [{"id": 11, "name": "Agent Tariq"}],
    "assignment": "${query.assignment || ''}",
    "status": "${query.status || 'all'}",
    "sort": "${query.sort || 'created_at'}",
    "direction": "${query.direction || 'desc'}",
    "userRole": "super_admin",
    "permissions": {"create": true, "calls": {"manage_status": true, "update": true, "delete": true}, "reports": {"view": true}}
  }
  </script>

  <script>
    window._indexData = JSON.parse(document.getElementById('calls-index-data').textContent);

    function callManager(config) {
      return {
        items: config.items || [],
        stats: config.stats || {},
        search: '',
        status: config.status || 'all',
        assignment: config.assignment || '',
        page: config.current_page || 1,
        perPage: config.per_page || 10,
        totalPages: config.last_page || 5,
        totalRecords: config.total || 50,
        sortBy: config.sort || 'created_at',
        sortDirection: config.direction || 'desc',
        isLoading: false,
        latestRequestId: 0,
        fetchAbortController: null,

        async fetchData() {
          const currentRequestId = ++this.latestRequestId;

          if (this.fetchAbortController) {
            try { this.fetchAbortController.abort(); } catch (e) {}
          }
          this.fetchAbortController = new AbortController();
          const signal = this.fetchAbortController.signal;

          this.isLoading = true;
          try {
            const params = new URLSearchParams();
            if (this.search) params.set('search', this.search);
            if (this.assignment) params.set('assignment', this.assignment);
            if (this.status && this.status !== 'all') params.set('status', this.status);
            params.set('page', this.page);
            params.set('sort', this.sortBy);
            params.set('direction', this.sortDirection);

            // Update browser URL history state cleanly
            const newUrl = window.location.pathname + '?' + params.toString();
            window.history.pushState({ page: this.page, search: this.search }, '', newUrl);

            const res = await fetch('/calls?' + params.toString(), { 
              signal,
              headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            
            // Drop stale response if newer request exists!
            if (currentRequestId !== this.latestRequestId) {
              return;
            }

            if (res.ok) {
              const data = await res.json();
              this.items = data.items || [];
              this.totalRecords = data.total ?? this.totalRecords;
              this.totalPages = data.last_page ?? this.totalPages;
            }
          } catch (e) {
            if (e.name !== 'AbortError') {
              console.error('Fetch error:', e);
            }
          } finally {
            if (currentRequestId === this.latestRequestId) {
              this.isLoading = false;
            }
          }
        }
      };
    }
  </script>

  <div x-data="callManager(window._indexData)" id="calls-app">
    <!-- Header / Nav -->
    <div class="flex justify-between items-center mb-6">
      <h2 class="text-2xl font-black">Calls Management</h2>
      <div class="space-x-2">
        <a href="/calls?assignment=assigned" class="px-3 py-1.5 rounded bg-blue-600 text-white text-xs font-bold">Assigned</a>
        <a href="/calls?assignment=unassigned" class="px-3 py-1.5 rounded bg-slate-200 text-slate-800 text-xs font-bold">Unassigned</a>
      </div>
    </div>

    <!-- Search box -->
    <div class="mb-4">
      <input type="text" id="callsSearchInput" x-model="search" @input="fetchData()" placeholder="Search CRM number..." class="px-4 py-2 border rounded text-sm w-64">
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-x-auto">
      <table class="w-full text-left text-sm calls-table">
        <thead class="bg-slate-50 border-b border-slate-200">
          <tr>
            <th class="p-3">Call #</th>
            <th class="p-3">Caller</th>
            <th class="p-3">Status</th>
            <th class="p-3">Assigned Beat</th>
            <th class="p-3">Actions</th>
          </tr>
        </thead>
        <tbody>
          <template x-for="call in items" :key="call.id">
            <tr class="border-b border-slate-100 call-row">
              <td class="p-3 font-mono font-bold" x-text="call.call_number"></td>
              <td class="p-3" x-text="call.caller_name"></td>
              <td class="p-3">
                <span class="px-2 py-0.5 rounded text-xs font-bold" x-text="call.status"></span>
              </td>
              <td class="p-3" x-text="call.office?.name || 'Unassigned'"></td>
              <td class="p-3">
                <a :href="'/calls/' + call.id" class="text-blue-600 font-bold hover:underline">View</a>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <nav role="navigation" aria-label="pagination" class="flex justify-between items-center mt-4 pagination">
      <span class="text-xs text-slate-500">Showing page <strong x-text="page"></strong> of <strong x-text="totalPages"></strong></span>
      <div class="space-x-2">
        <button @click="page--; fetchData()" :disabled="page <= 1" class="px-3 py-1 border rounded text-xs">Previous</button>
        <button @click="page++; fetchData()" :disabled="page >= totalPages" class="px-3 py-1 border rounded text-xs">Next</button>
      </div>
    </nav>
  </div>
</body>
</html>`;
}

// Render Dashboard Page
function renderDashboardPage() {
  return `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Dashboard - NHMP 130 Helpline</title>
  <link rel="stylesheet" href="/build/assets/app-BXZ6LzFI.css">
</head>
<body class="p-6 bg-slate-50 font-sans">
  <main>
    <h1 class="text-3xl font-black mb-6">Operations Command Center</h1>
    <div class="grid grid-cols-4 gap-4 mb-6">
      <div class="kpi-card p-4 bg-white rounded-xl shadow-sm border border-slate-200" data-metric="total">
        <p class="text-xs text-slate-500 font-bold uppercase">Total Calls Today</p>
        <h3 class="text-2xl font-black text-blue-600">1,248</h3>
      </div>
      <div class="kpi-card p-4 bg-white rounded-xl shadow-sm border border-slate-200" data-metric="pending">
        <p class="text-xs text-slate-500 font-bold uppercase">Pending Response</p>
        <h3 class="text-2xl font-black text-rose-600">14</h3>
      </div>
      <div class="kpi-card p-4 bg-white rounded-xl shadow-sm border border-slate-200" data-metric="completed">
        <p class="text-xs text-slate-500 font-bold uppercase">Completed Operations</p>
        <h3 class="text-2xl font-black text-emerald-600">1,180</h3>
      </div>
      <div class="kpi-card p-4 bg-white rounded-xl shadow-sm border border-slate-200" data-metric="avg_time">
        <p class="text-xs text-slate-500 font-bold uppercase">Avg Response Time</p>
        <h3 class="text-2xl font-black text-amber-600">4.2 min</h3>
      </div>
    </div>

    <!-- Map & Charts -->
    <div class="grid grid-cols-2 gap-6">
      <div id="map" class="h-64 bg-slate-200 rounded-xl flex items-center justify-center font-bold text-slate-500">
        Leaflet Patrol Map Container
      </div>
      <div class="p-4 bg-white rounded-xl border border-slate-200">
        <canvas id="trafficChart" class="chartjs-render-monitor h-56 w-full"></canvas>
      </div>
    </div>
  </main>
</body>
</html>`;
}

// Render Generic Management / Report Page
function renderGenericPage(title, heading) {
  return `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>${title} - NHMP 130 Helpline</title>
  <link rel="stylesheet" href="/build/assets/app-BXZ6LzFI.css">
</head>
<body class="p-6 bg-slate-50 font-sans">
  <main>
    <h1 class="text-2xl font-black mb-4">${heading}</h1>
    <div class="bg-white p-6 rounded-xl border border-slate-200">
      <table class="w-full text-left text-sm">
        <thead>
          <tr class="border-b">
            <th class="p-2">Name</th>
            <th class="p-2">Category</th>
            <th class="p-2">Status</th>
            <th class="p-2">Total Count</th>
          </tr>
        </thead>
        <tbody>
          <tr class="border-b"><td class="p-2">Emergency Breakdown</td><td class="p-2">Technical</td><td class="p-2">Active</td><td class="p-2">420</td></tr>
          <tr class="border-b"><td class="p-2">Accident Reporting</td><td class="p-2">Emergency</td><td class="p-2">Active</td><td class="p-2">130</td></tr>
          <tr class="border-b"><td class="p-2">Travel Guidance</td><td class="p-2">Information</td><td class="p-2">Active</td><td class="p-2">580</td></tr>
          <tr class="font-bold bg-slate-50"><td class="p-2">TOTAL</td><td class="p-2">-</td><td class="p-2">-</td><td class="p-2">1,130</td></tr>
        </tbody>
      </table>
    </div>
  </main>
</body>
</html>`;
}

// Render Login Page
function renderLoginPage() {
  return `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Sign in - NHMP 130 Helpline</title>
  <link rel="stylesheet" href="/build/assets/app-BXZ6LzFI.css">
</head>
<body class="flex items-center justify-center min-h-screen bg-slate-900 text-slate-800">
  <div class="w-full max-w-md p-8 bg-white rounded-2xl shadow-xl">
    <h2 class="text-2xl font-black mb-4 text-center text-blue-900">NHMP 130 Sign in</h2>
    <form action="/login" method="POST" class="space-y-4">
      <div>
        <label class="block text-xs font-bold mb-1 text-slate-600">Username</label>
        <input type="text" id="username" name="username" class="w-full px-4 py-2 border rounded-lg" required>
      </div>
      <div>
        <label class="block text-xs font-bold mb-1 text-slate-600">Password</label>
        <div class="relative">
          <input type="password" id="password" name="password" class="w-full px-4 py-2 border rounded-lg" required>
          <button type="button" id="togglePassword" onclick="const p=document.getElementById('password'); p.type = p.type==='password'?'text':'password';" class="absolute right-3 top-2.5 text-xs text-slate-400">Show</button>
        </div>
      </div>
      <button type="submit" id="loginButton" class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-bold">Sign In</button>
    </form>
  </div>
</body>
</html>`;
}

// Main HTTP request handler
const server = http.createServer((req, res) => {
  const parsedUrl = url.parse(req.url, true);
  const pathname = parsedUrl.pathname;
  const query = parsedUrl.query;

  // Static Asset Handling
  if (pathname.startsWith('/build/') || pathname.startsWith('/vendor/') || pathname.startsWith('/js/') || pathname.startsWith('/node_modules/')) {
    let filePath;
    if (pathname.startsWith('/node_modules/')) {
      filePath = path.join(BASE_DIR, pathname);
    } else {
      filePath = path.join(BASE_DIR, 'public', pathname);
    }

    if (fs.existsSync(filePath) && fs.statSync(filePath).isFile()) {
      const ext = path.extname(filePath);
      const mimeTypes = {
        '.css': 'text/css',
        '.js': 'application/javascript',
        '.woff2': 'font/woff2',
        '.woff': 'font/woff',
        '.ttf': 'font/ttf',
        '.svg': 'image/svg+xml',
        '.png': 'image/png',
        '.jpg': 'image/jpeg',
      };
      res.writeHead(200, { 'Content-Type': mimeTypes[ext] || 'application/octet-stream' });
      fs.createReadStream(filePath).pipe(res);
      return;
    }
  }

  // Health probe
  if (pathname === '/health' || pathname === '/up') {
    res.writeHead(200, { 'Content-Type': 'text/plain' });
    res.end('OK');
    return;
  }

  // Login
  if (pathname === '/login') {
    if (req.method === 'POST') {
      res.writeHead(302, { 'Location': '/dashboard', 'Set-Cookie': 'nhmp_session=authenticated; Path=/' });
      res.end();
      return;
    }
    res.writeHead(200, { 'Content-Type': 'text/html' });
    res.end(renderLoginPage());
    return;
  }

  // Dashboard
  if (pathname === '/dashboard') {
    res.writeHead(200, { 'Content-Type': 'text/html' });
    res.end(renderDashboardPage());
    return;
  }

  // Calls Management
  if (pathname === '/calls') {
    const isJson = (req.headers.accept && req.headers.accept.includes('application/json')) || req.headers['x-requested-with'] === 'XMLHttpRequest';
    if (isJson) {
      let items = [...allMockCalls];
      if (query.search) {
        const q = query.search.toLowerCase();
        items = items.filter(c => c.call_number.toLowerCase().includes(q) || c.caller_name.toLowerCase().includes(q));
      }
      if (query.status && query.status !== 'all') {
        items = items.filter(c => c.status === query.status);
      }
      res.writeHead(200, { 'Content-Type': 'application/json' });
      res.end(JSON.stringify({
        items: items.slice(0, 10),
        total: items.length,
        current_page: 1,
        last_page: Math.ceil(items.length / 10) || 1,
        stats: { total: items.length, pending: 12, in_progress: 13, tiger_reached: 12, completed: 13 }
      }));
      return;
    }

    res.writeHead(200, { 'Content-Type': 'text/html' });
    res.end(renderCallsPage(query));
    return;
  }

  // Call Details
  if (pathname.match(/^\/calls\/\d+$/)) {
    const id = pathname.split('/').pop();
    res.writeHead(200, { 'Content-Type': 'text/html' });
    res.end(`<!DOCTYPE html>
<html>
<head><title>Call Details - CRM-2026-0000${id}</title><link rel="stylesheet" href="/build/assets/app-BXZ6LzFI.css"></head>
<body class="p-6 bg-slate-50 font-sans">
  <div class="max-w-2xl bg-white p-6 rounded-xl border border-slate-200">
    <h1 class="text-xl font-bold mb-2">Call Details: CRM-2026-0000${id}</h1>
    <p class="text-sm text-slate-600 mb-4">Caller: Citizen Traveler | Contact: 03001234567 | Road: M-2 Motorway</p>
    <div class="mb-4">
      <label class="block text-xs font-bold mb-1">Status</label>
      <select id="status" name="status" class="border p-2 rounded text-sm w-48">
        <option value="pending">Pending</option>
        <option value="in_progress">In Progress</option>
        <option value="tiger_reached">Tiger Reached</option>
        <option value="completed">Completed</option>
      </select>
    </div>
    <button id="saveStatusBtn" onclick="document.getElementById('statusFeedback').innerText='Status Updated to ' + document.getElementById('status').value + ' Successfully';" class="px-4 py-2 bg-blue-600 text-white rounded font-bold text-sm">Save Status</button>
    <div id="statusFeedback" class="mt-2 text-sm text-green-600 font-bold"></div>
  </div>
</body>
</html>`);
    return;
  }

  // User Management
  if (pathname === '/mgmt/users') {
    res.writeHead(200, { 'Content-Type': 'text/html' });
    res.end(renderUsersPage());
    return;
  }

  // Reports
  if (pathname.startsWith('/reports/')) {
    res.writeHead(200, { 'Content-Type': 'text/html' });
    res.end(renderGenericPage('Analytical Report', 'Operations Summary & Analytics'));
    return;
  }

  // Other Management routes
  if (pathname.startsWith('/mgmt/')) {
    res.writeHead(200, { 'Content-Type': 'text/html' });
    res.end(renderGenericPage('Administrative Configuration', 'System Settings'));
    return;
  }

  // Default redirect or 404
  if (pathname === '/') {
    res.writeHead(302, { 'Location': '/dashboard' });
    res.end();
    return;
  }

  res.writeHead(404, { 'Content-Type': 'text/plain' });
  res.end('Not Found');
});

server.listen(PORT, '127.0.0.1', () => {
  console.log(`Local NHMP Test Server running on http://127.0.0.1:${PORT}`);
});
