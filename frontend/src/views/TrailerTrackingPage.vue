<template>
  <div class="page-wrap">
    <div class="page-inner">
      <div class="panel top-panel">
        <div class="top-row">
          <div>
            <h1 class="page-title">Trailer Tracking Report</h1>
          </div>

          <div class="search-actions">
            <select
                v-model="selectedOwner"
                class="search-input"
                :disabled="loading || owners.length === 0"
                @change="loadReport"
            >
              <option value="">Select Owner</option>
              <option
                  v-for="owner in owners"
                  :key="owner"
                  :value="owner"
              >
                {{ owner }}
              </option>
            </select>

            <button class="btn btn-dark" :disabled="loading" @click="loadReport">
              {{ loading ? 'Loading...' : 'Refresh' }}
            </button>

            <button class="btn btn-light" :disabled="loading" @click="clearFilters">
              Clear
            </button>
          </div>
        </div>

        <div class="chip-row">
          <button class="chip" disabled>
            Total Trailers
            <span class="chip-count">{{ summary.total_trailers ?? 0 }}</span>
          </button>

          <button class="chip" disabled>
            Assigned
            <span class="chip-count">{{ summary.assigned_count ?? 0 }}</span>
          </button>

          <button class="chip" disabled>
            Unassigned
            <span class="chip-count">{{ summary.unassigned_count ?? 0 }}</span>
          </button>
        </div>

        <div class="meta-row">
          <span class="meta-pill">Visible rows: {{ rows.length }}</span>
          <span class="meta-pill">Selected owner: {{ selectedOwner || 'None' }}</span>
        </div>
      </div>

      <div v-if="err" class="panel error-box">
        {{ err }}
      </div>

      <div ref="tableWrap" class="panel">
        <DataTable
            :key="tableKey"
            class="display nowrap compact stripe row-border hover w-full"
            :data="rows"
            :columns="columns"
            :options="options"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import DataTable from 'datatables.net-vue3'
import DataTablesCore from 'datatables.net-dt'
import Responsive from 'datatables.net-responsive-dt'
import FixedHeader from 'datatables.net-fixedheader-dt'
import { fetchOwners, fetchTrailerTracking } from '../api/report'

DataTable.use(DataTablesCore)
DataTable.use(Responsive)
DataTable.use(FixedHeader)

const selectedOwner = ref('')
const owners = ref([])
const rows = ref([])
const summary = ref({})
const err = ref('')
const loading = ref(false)
const tableKey = ref(0)
const tableWrap = ref(null)

function esc(value) {
  return String(value ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;')
}

function unassignedBadge() {
  return '<span class="badge-missing">Unassigned</span>'
}

function renderDriver(value) {
  if (value === '' || value === null || value === undefined || value === 'Unassigned') {
    return unassignedBadge()
  }

  return esc(value)
}

function renderText(value) {
  if (value === '' || value === null || value === undefined) {
    return '—'
  }

  return esc(value)
}

const columns = [
  {
    title: 'Trailer',
    data: 'trailer_display',
    render: (data) => renderText(data),
  },
  {
    title: 'Vehicle Number',
    data: 'vehicle_number',
    render: (data) => renderText(data),
  },
  {
    title: 'License Plate',
    data: 'license_plate',
    render: (data) => renderText(data),
  },
  {
    title: 'Owner',
    data: 'owner',
    render: (data) => renderText(data),
  },
  {
    title: 'Current Assigned Driver',
    data: 'current_assigned_driver',
    render: (data) => renderDriver(data),
  },
]

const options = {
  paging: true,
  pageLength: 25,
  lengthMenu: [
    [25, 50, 100, -1],
    [25, 50, 100, 'All'],
  ],
  searching: true,
  ordering: true,
  info: true,
  responsive: true,
  fixedHeader: true,
  autoWidth: false,
  order: [[0, 'asc']],
}

function clearFilters() {
  selectedOwner.value = ''
  rows.value = []
  summary.value = {
    total_trailers: 0,
    assigned_count: 0,
    unassigned_count: 0,
  }
  err.value = ''
  tableKey.value += 1
}

async function loadOwners() {
  const data = await fetchOwners()
  owners.value = data.owners || []
}

async function loadReport() {
  err.value = ''
  loading.value = true

  try {
    if (!selectedOwner.value) {
      rows.value = []
      summary.value = {
        total_trailers: 0,
        assigned_count: 0,
        unassigned_count: 0,
      }
      tableKey.value += 1
      return
    }

    const data = await fetchTrailerTracking({
      owner: selectedOwner.value,
    })

    rows.value = data.rows || []
    summary.value = data.summary || {
      total_trailers: 0,
      assigned_count: 0,
      unassigned_count: 0,
    }
    tableKey.value += 1
  } catch (error) {
    rows.value = []
    summary.value = {
      total_trailers: 0,
      assigned_count: 0,
      unassigned_count: 0,
    }
    err.value = error?.message || 'Failed to load trailer tracking report.'
    tableKey.value += 1
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  try {
    await loadOwners()
  } catch (error) {
    err.value = error?.message || 'Failed to load owners.'
  }
})
</script>

<style>
@import "datatables.net-dt";
@import "datatables.net-responsive-dt";
@import "datatables.net-fixedheader-dt";

body {
  margin: 0;
  font-family: Arial, sans-serif;
  background: #f8fafc;
  color: #0f172a;
}

.page-wrap {
  min-height: 100vh;
  padding: 16px;
}

.page-inner {
  max-width: 1700px;
  margin: 0 auto;
}

.panel {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 18px;
  padding: 16px;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
  margin-bottom: 16px;
}

.top-row {
  display: flex;
  gap: 16px;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
}

.page-title {
  margin: 0;
  font-size: 28px;
  font-weight: 700;
}

.search-actions {
  display: grid;
  grid-template-columns: minmax(320px, 1fr) auto auto;
  gap: 8px;
  width: min(760px, 100%);
}

.search-input {
  height: 40px;
  border: 1px solid #cbd5e1;
  border-radius: 12px;
  padding: 0 12px;
  font-size: 14px;
  background: #fff;
  color: #0f172a;
}

.btn {
  height: 40px;
  border-radius: 12px;
  padding: 0 16px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
}

.btn:disabled,
.chip:disabled {
  opacity: 0.6;
  cursor: default;
}

.btn-dark {
  border: 1px solid #0f172a;
  background: #0f172a;
  color: #fff;
}

.btn-light {
  border: 1px solid #cbd5e1;
  background: #fff;
  color: #334155;
}

.chip-row {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 14px;
}

.chip {
  border: 1px solid #cbd5e1;
  background: #fff;
  color: #334155;
  border-radius: 999px;
  padding: 7px 12px;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
}

.chip-count,
.meta-pill {
  display: inline-block;
  background: #f1f5f9;
  color: #334155;
  border-radius: 999px;
  font-size: 11px;
}

.chip-count {
  margin-left: 8px;
  padding: 2px 8px;
}

.meta-row {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  margin-top: 12px;
}

.meta-pill {
  padding: 5px 10px;
}

.badge-missing {
  display: inline-block;
  background: #fef2f2;
  color: #b91c1c;
  border: 1px solid #fecaca;
  border-radius: 999px;
  padding: 2px 7px;
  font-size: 10px;
  font-weight: 700;
  line-height: 1.1;
}

.error-box {
  color: #b91c1c;
}

.driver-link {
  color: #2563eb;
  font-weight: 700;
  text-decoration: none;
}

.driver-link:hover {
  text-decoration: underline;
}

div.dt-container div.dt-layout-row {
  margin: 0 0 8px 0;
}

div.dt-container .dt-length,
div.dt-container .dt-search,
div.dt-container .dt-info,
div.dt-container .dt-paging {
  font-size: 12px;
}

div.dt-container .dt-length select,
div.dt-container .dt-search input {
  min-height: 32px;
  height: 32px;
  padding: 4px 10px;
  border: 1px solid #cbd5e1;
  border-radius: 10px;
  font-size: 12px;
}

table.dataTable thead th {
  padding: 9px 10px !important;
  font-size: 13px;
  font-weight: 700;
  border-bottom: 1px solid #e2e8f0 !important;
  line-height: 1.15;
}

table.dataTable tbody td {
  padding: 8px 10px !important;
  font-size: 13px;
  line-height: 1.15;
  border-bottom: 1px solid #e2e8f0 !important;
  vertical-align: middle;
}

table.dataTable.compact thead th {
  padding-top: 8px !important;
  padding-bottom: 8px !important;
}

table.dataTable.compact tbody td {
  padding-top: 7px !important;
  padding-bottom: 7px !important;
}

table.dataTable tbody tr {
  height: auto;
}

@media (max-width: 900px) {
  .search-actions {
    grid-template-columns: 1fr;
  }
}
</style>