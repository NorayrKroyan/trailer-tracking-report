<template>
  <div class="page-wrap">
    <div class="page-inner">
      <div class="panel top-panel">
        <div class="top-row">
          <div class="title-block">
            <h1 class="page-title">Trailer Tracking Report</h1>
            <div class="page-subtitle">
              Trailer history, load count, revenue, and recent job by date range.
            </div>
          </div>

          <div class="search-actions">
            <select
                v-model="selectedOwner"
                class="search-input"
                :disabled="loading || owners.length === 0"
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

            <input
                v-model="selectedStartDate"
                type="date"
                class="search-input"
                :max="selectedEndDate"
                :disabled="loading"
            />

            <input
                v-model="selectedEndDate"
                type="date"
                class="search-input"
                :min="selectedStartDate"
                :disabled="loading"
            />

            <button class="btn btn-dark" :disabled="loading" @click="loadReport">
              {{ loading ? 'Loading...' : 'Refresh' }}
            </button>

            <button class="btn btn-light" :disabled="loading" @click="clearFilters">
              Clear
            </button>
          </div>
        </div>

        <div class="chip-row">
          <span class="meta-pill">Owner: {{ selectedOwner || 'None' }}</span>
          <span class="meta-pill">Start: {{ formatChipDate(selectedStartDate) }}</span>
          <span class="meta-pill">End: {{ formatChipDate(selectedEndDate) }}</span>
        </div>
      </div>

      <div class="summary-grid">
        <div class="panel summary-card">
          <div class="summary-label">Total Trailers</div>
          <div class="summary-value">{{ summary.total_trailers ?? 0 }}</div>
        </div>

        <div class="panel summary-card">
          <div class="summary-label">Total Loads</div>
          <div class="summary-value">{{ summary.total_load_count ?? 0 }}</div>
        </div>

        <div class="panel summary-card">
          <div class="summary-label">Total Revenue</div>
          <div class="summary-value">{{ formatCurrency(summary.total_load_revenue ?? 0) }}</div>
        </div>

        <div class="panel summary-card">
          <div class="summary-label">With Driver History</div>
          <div class="summary-value">{{ summary.trailers_with_driver_history ?? 0 }}</div>
        </div>
      </div>

      <div v-if="err" class="panel error-box">
        {{ err }}
      </div>

      <div ref="tableWrap" class="panel table-panel">
        <DataTable
            :key="tableKey"
            class="display compact stripe row-border hover report-table nowrap"
            :data="rows"
            :columns="columns"
            :options="options"
        />
      </div>
    </div>

    <TrailerVehicleEditorModal
        :open="vehicleEditor.open"
        :loading="vehicleEditor.loading"
        :saving="vehicleEditor.saving"
        :subtitle="vehicleEditor.subtitle"
        :form="vehicleForm"
        :errors="vehicleErrors"
        :form-error="vehicleFormError"
        :assignment-history="vehicleEditor.assignmentHistory"
        :report-meta="vehicleEditor.reportMeta"
        @close="closeVehicleEditor"
        @save="saveVehicleEditorModal"
    />

    <TrailerContactDriverEditorModal
        :open="driverEditor.open"
        :loading="driverEditor.loading"
        :saving="driverEditor.saving"
        :form="driverForm"
        :errors="driverErrors"
        :form-error="driverFormError"
        :lookups="driverLookups"
        :driver-toggle="driverToggle"
        @close="closeDriverEditor"
        @save="saveDriverEditorModal"
        @update:driverToggle="driverToggle = $event"
    />
  </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import DataTable from 'datatables.net-vue3'
import DataTablesCore from 'datatables.net-dt'
import Responsive from 'datatables.net-responsive-dt'
import FixedHeader from 'datatables.net-fixedheader-dt'
import {
  fetchDriverEditor,
  fetchOwners,
  fetchTrailerTracking,
  fetchVehicleEditor,
  saveDriverEditor,
  saveVehicleEditor,
} from '../api/report'
import TrailerVehicleEditorModal from '../components/TrailerVehicleEditorModal.vue'
import TrailerContactDriverEditorModal from '../components/TrailerContactDriverEditorModal.vue'

DataTable.use(DataTablesCore)
DataTable.use(Responsive)
DataTable.use(FixedHeader)

const selectedOwner = ref('')
const owners = ref([])
const rows = ref([])
const summary = ref({
  total_trailers: 0,
  total_load_count: 0,
  total_load_revenue: 0,
  trailers_with_driver_history: 0,
})
const err = ref('')
const loading = ref(false)
const tableKey = ref(0)
const tableWrap = ref(null)
const selectedStartDate = ref(defaultStartDate())
const selectedEndDate = ref(todayDateString())

const vehicleEditor = reactive({
  open: false,
  loading: false,
  saving: false,
  subtitle: '',
  assignmentHistory: [],
  reportMeta: {
    load_count: 0,
    load_revenue: 0,
    recent_job: '',
  },
})

const vehicleForm = reactive({
  id_vehicle: null,
  vehicle_number: '',
  vehicle_name: '',
  owner: '',
  license_plate: '',
})

const vehicleErrors = reactive({})
const vehicleFormError = ref('')

const driverEditor = reactive({
  open: false,
  loading: false,
  saving: false,
})

const driverForm = reactive({
  contact: {
    id_contact: null,
    first_name: '',
    last_name: '',
    phone_number: '',
    email: '',
    address: '',
    state: '',
  },
  driver: {
    id_driver: null,
    is_driver: 1,
    driver_shift: '',
    spanish_language: 0,
    id_vehicle: null,
    id_trailer: null,
    id_device: '',
    mobile_app_pin: '',
    status: '',
    id_carrier: null,
    idprojects: null,
    tcs_fuel_card_number: '',
    tcs_fuel_card_pin: '',
    tcs_fuel_card_limit: '',
    tcs_fuel_card_last_updated: '',
  },
})

const driverErrors = reactive({})
const driverFormError = ref('')
const driverLookups = reactive({
  states: [],
  trailers: [],
  trucks: [],
  carriers: [],
  projects: [],
  statuses: [],
})
const driverToggle = ref(true)

function todayDateString() {
  const today = new Date()
  const year = today.getFullYear()
  const month = String(today.getMonth() + 1).padStart(2, '0')
  const day = String(today.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

function defaultStartDate() {
  const date = new Date()
  date.setDate(date.getDate() - 30)
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

function esc(value) {
  return String(value ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;')
}

function parseJson(value) {
  try {
    return JSON.parse(value)
  } catch {
    return null
  }
}

function formatChipDate(value) {
  if (!value) return '—'
  const parts = String(value).split('-')
  if (parts.length !== 3) return value
  return `${parts[1]}-${parts[2]}-${parts[0]}`
}

function formatCurrency(value) {
  const amount = Number(value ?? 0)

  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Number.isFinite(amount) ? amount : 0)
}

function renderText(value) {
  if (value === '' || value === null || value === undefined) {
    return '—'
  }

  return esc(value)
}

function renderInteger(value, type) {
  const amount = Number(value ?? 0)

  if (type !== 'display') {
    return Number.isFinite(amount) ? amount : 0
  }

  return String(Number.isFinite(amount) ? amount : 0)
}

function renderCurrency(value, type) {
  const amount = Number(value ?? 0)

  if (type !== 'display') {
    return Number.isFinite(amount) ? amount : 0
  }

  return formatCurrency(Number.isFinite(amount) ? amount : 0)
}

function unassignedBadge() {
  return '<span class="badge-missing">No driver history</span>'
}

function vehicleAssignmentRowsFromRow(row) {
  const entries = Array.isArray(row?.driver_history_entries) ? row.driver_history_entries : []

  return entries.map((entry) => ({
    date: entry?.assigned_date_display || '—',
    action: 'Assigned',
    driver: entry?.driver_name || '—',
    current: String(entry?.unassigned_date_display || '').toLowerCase() === 'current',
  }))
}

function clearReactiveObject(obj) {
  Object.keys(obj).forEach((key) => delete obj[key])
}

function normalizeCollection(value) {
  if (Array.isArray(value)) {
    return value
  }

  if (value && typeof value === 'object') {
    return Object.entries(value).map(([key, item]) => {
      if (item && typeof item === 'object' && !Array.isArray(item)) {
        return { ...item, __lookupKey: key }
      }

      return {
        value: key,
        label: item,
        name: item,
        projectname: item,
        carrier_name: item,
        vehicle_name: item,
        vehicle_number: item,
        status: item,
        __lookupKey: key,
      }
    })
  }

  return []
}

function firstCollection(...values) {
  for (const value of values) {
    const normalized = normalizeCollection(value)
    if (normalized.length) {
      return normalized
    }
  }

  return []
}

function stripVehiclePrefix(value) {
  return String(value ?? '')
      .replace(/^Vehicle\s*#\s*/i, '')
      .trim()
}

function normalizeStateOptions(items) {
  return items.map((item) => {
    const value =
        item?.value ??
        item?.id ??
        item?.id_state ??
        item?.state_id ??
        item?.abbr ??
        item?.state ??
        item?.__lookupKey ??
        ''

    const label =
        item?.label ??
        item?.name ??
        item?.state ??
        item?.abbr ??
        String(value)

    return {
      ...item,
      id: value,
      value,
      name: label,
      label,
    }
  })
}

function normalizeCarrierOptions(items) {
  return items.map((item) => {
    const value =
        item?.value ??
        item?.id_carrier ??
        item?.idcarrier ??
        item?.id ??
        item?.carrier_id ??
        item?.__lookupKey ??
        ''

    const label =
        item?.label ??
        item?.name ??
        item?.carrier_name ??
        item?.company_name ??
        item?.company ??
        String(item?.value ?? item?.__lookupKey ?? '')

    return {
      ...item,
      id: value,
      id_carrier: value,
      value,
      name: label,
      carrier_name: label,
      label,
    }
  })
}

function normalizeProjectOptions(items) {
  return items.map((item) => {
    const value =
        item?.value ??
        item?.idprojects ??
        item?.id_project ??
        item?.id ??
        item?.project_id ??
        item?.__lookupKey ??
        ''

    const label =
        item?.label ??
        item?.projectname ??
        item?.name ??
        String(item?.value ?? item?.__lookupKey ?? '')

    return {
      ...item,
      id: value,
      idprojects: value,
      value,
      name: label,
      projectname: label,
      label,
    }
  })
}

function normalizeVehicleOptions(items) {
  return items.map((item) => {
    const value =
        item?.value ??
        item?.id_vehicle ??
        item?.id_truck ??
        item?.id_trailer ??
        item?.vehicle_id ??
        item?.id ??
        item?.__lookupKey ??
        ''

    const label = stripVehiclePrefix(
        item?.label ??
        item?.vehicle_name ??
        item?.vehicle_number ??
        item?.number ??
        item?.name ??
        String(item?.value ?? item?.__lookupKey ?? '')
    )

    return {
      ...item,
      id: value,
      id_vehicle: item?.id_vehicle ?? value,
      value,
      name: label,
      label,
      vehicle_name: label,
      vehicle_number: stripVehiclePrefix(item?.vehicle_number ?? label),
    }
  })
}

function normalizeStatusOptions(items) {
  return items.map((item) => {
    const value = item?.value ?? item?.id ?? item?.status ?? item?.__lookupKey ?? item ?? ''
    const label = item?.label ?? item?.name ?? item?.status ?? String(value)

    return {
      ...((typeof item === 'object' && item !== null) ? item : {}),
      value,
      label,
      name: label,
    }
  })
}

function resetVehicleEditorState() {
  vehicleEditor.open = false
  vehicleEditor.loading = false
  vehicleEditor.saving = false
  vehicleEditor.subtitle = ''
  vehicleEditor.assignmentHistory = []
  vehicleEditor.reportMeta = {
    load_count: 0,
    load_revenue: 0,
    recent_job: '',
  }

  vehicleForm.id_vehicle = null
  vehicleForm.vehicle_number = ''
  vehicleForm.vehicle_name = ''
  vehicleForm.owner = ''
  vehicleForm.license_plate = ''

  clearReactiveObject(vehicleErrors)
  vehicleFormError.value = ''
}

function closeVehicleEditor() {
  resetVehicleEditorState()
}

function resetDriverEditorState() {
  driverEditor.open = false
  driverEditor.loading = false
  driverEditor.saving = false

  Object.assign(driverForm.contact, {
    id_contact: null,
    first_name: '',
    last_name: '',
    phone_number: '',
    email: '',
    address: '',
    state: '',
  })

  Object.assign(driverForm.driver, {
    id_driver: null,
    is_driver: 1,
    driver_shift: '',
    spanish_language: 0,
    id_vehicle: null,
    id_trailer: null,
    id_device: '',
    mobile_app_pin: '',
    status: '',
    id_carrier: null,
    idprojects: null,
    tcs_fuel_card_number: '',
    tcs_fuel_card_pin: '',
    tcs_fuel_card_limit: '',
    tcs_fuel_card_last_updated: '',
  })

  driverLookups.states = []
  driverLookups.trailers = []
  driverLookups.trucks = []
  driverLookups.carriers = []
  driverLookups.projects = []
  driverLookups.statuses = []
  driverToggle.value = true

  clearReactiveObject(driverErrors)
  driverFormError.value = ''
}

function closeDriverEditor() {
  resetDriverEditorState()
}

async function openVehicleEditor(row) {
  resetVehicleEditorState()

  vehicleEditor.open = true
  vehicleEditor.loading = true
  vehicleEditor.subtitle = row?.vehicle_name || row?.vehicle_number || row?.trailer_display || `Trailer #${row?.id_vehicle ?? ''}`
  vehicleEditor.assignmentHistory = vehicleAssignmentRowsFromRow(row)
  vehicleEditor.reportMeta = {
    load_count: row?.load_count ?? 0,
    load_revenue: row?.load_revenue ?? 0,
    recent_job: row?.recent_job || '',
  }

  try {
    const data = await fetchVehicleEditor(row.id_vehicle)
    const vehicle = data.vehicle || {}

    vehicleForm.id_vehicle = vehicle.id_vehicle
    vehicleForm.vehicle_number = vehicle.vehicle_number || ''
    vehicleForm.vehicle_name = vehicle.vehicle_name || ''
    vehicleForm.owner = vehicle.owner || ''
    vehicleForm.license_plate = vehicle.license_plate || ''
  } catch (error) {
    vehicleFormError.value = error?.message || 'Failed to load vehicle editor.'
  } finally {
    vehicleEditor.loading = false
  }
}

async function openDriverEditor(entry) {
  resetDriverEditorState()

  driverEditor.open = true
  driverEditor.loading = true

  try {
    const data = await fetchDriverEditor(entry.contact_id)
    const lookups = data?.lookups || {}

    Object.assign(driverForm.contact, {
      id_contact: null,
      first_name: '',
      last_name: '',
      phone_number: '',
      email: '',
      address: '',
      state: '',
      ...(data.contact || {}),
    })

    Object.assign(driverForm.driver, {
      id_driver: null,
      is_driver: 1,
      driver_shift: '',
      spanish_language: 0,
      id_vehicle: null,
      id_trailer: null,
      id_device: '',
      mobile_app_pin: '',
      status: '',
      id_carrier: null,
      idprojects: null,
      tcs_fuel_card_number: '',
      tcs_fuel_card_pin: '',
      tcs_fuel_card_limit: '',
      tcs_fuel_card_last_updated: '',
      ...(data.driver || {}),
    })

    driverLookups.states = normalizeStateOptions(
        firstCollection(
            lookups.states,
            lookups.state_options,
            data.states
        )
    )

    driverLookups.carriers = normalizeCarrierOptions(
        firstCollection(
            lookups.carriers,
            lookups.carrier_options,
            lookups.carrier_list,
            lookups.carrierMap,
            lookups.carrier_map,
            data.carriers
        )
    )

    driverLookups.projects = normalizeProjectOptions(
        firstCollection(
            lookups.projects,
            lookups.project_options,
            lookups.project_list,
            lookups.projectMap,
            lookups.project_map,
            data.projects
        )
    )

    driverLookups.trucks = normalizeVehicleOptions(
        firstCollection(
            lookups.trucks,
            lookups.truck_options,
            lookups.truck_list,
            data.trucks
        )
    )

    driverLookups.trailers = normalizeVehicleOptions(
        firstCollection(
            lookups.trailers,
            lookups.trailer_options,
            lookups.trailer_list,
            data.trailers
        )
    )

    driverLookups.statuses = normalizeStatusOptions(
        firstCollection(
            lookups.statuses,
            lookups.status_options,
            data.statuses
        )
    )

    driverToggle.value = Number(driverForm.driver.is_driver ?? 1) === 1
  } catch (error) {
    driverFormError.value = error?.message || 'Failed to load contact / driver editor.'
  } finally {
    driverEditor.loading = false
  }
}

async function saveVehicleEditorModal() {
  if (!vehicleForm.id_vehicle) {
    return
  }

  vehicleEditor.saving = true
  vehicleFormError.value = ''

  try {
    await saveVehicleEditor(vehicleForm.id_vehicle, {
      vehicle: {
        vehicle_number: vehicleForm.vehicle_number,
        vehicle_name: vehicleForm.vehicle_name,
        owner: vehicleForm.owner,
        license_plate: vehicleForm.license_plate,
      },
    })

    await loadReport()
    closeVehicleEditor()
  } catch (error) {
    vehicleFormError.value = error?.message || 'Failed to save vehicle.'
  } finally {
    vehicleEditor.saving = false
  }
}

async function saveDriverEditorModal() {
  if (!driverForm.contact.id_contact) {
    return
  }

  driverEditor.saving = true
  driverFormError.value = ''

  try {
    await saveDriverEditor(driverForm.contact.id_contact, {
      contact: {
        first_name: driverForm.contact.first_name,
        last_name: driverForm.contact.last_name,
        phone_number: driverForm.contact.phone_number,
        email: driverForm.contact.email,
        address: driverForm.contact.address,
        state: driverForm.contact.state,
      },
      driver: {
        ...driverForm.driver,
        is_driver: driverToggle.value ? 1 : 0,
      },
    })

    await loadReport()
    closeDriverEditor()
  } catch (error) {
    driverFormError.value = error?.message || 'Failed to save contact / driver.'
  } finally {
    driverEditor.saving = false
  }
}

function renderTrailer(data, type, row) {
  const text = row?.vehicle_name || data || row?.vehicle_number || `Trailer #${row?.id_vehicle ?? ''}`

  if (type !== 'display') {
    return text
  }

  return `
    <a
      href="#"
      class="report-link js-trailer-link"
      data-row='${esc(JSON.stringify(row))}'
    >
      ${esc(text)}
    </a>
  `
}

function renderDriverHistory(data, type, row) {
  const entries = Array.isArray(row?.driver_history_entries) ? row.driver_history_entries : []

  if (type !== 'display') {
    return row?.driver_history_plain || ''
  }

  if (!entries.length) {
    return unassignedBadge()
  }

  return entries
      .map((entry) => {
        const label = `${entry.driver_name} - ${entry.assigned_date_display} - ${entry.unassigned_date_display}`

        return `
        <a
          href="#"
          class="report-link driver-history-link js-driver-link"
          data-entry='${esc(JSON.stringify(entry))}'
        >
          ${esc(label)}
        </a>
      `
      })
      .join('<br>')
}

const columns = [
  {
    title: 'Trailer Number',
    data: 'vehicle_name',
    render: (data, type, row) => renderTrailer(data, type, row),
  },
  {
    title: 'Drivers',
    data: 'driver_history_entries',
    render: (data, type, row) => renderDriverHistory(data, type, row),
  },
  {
    title: 'Load Count',
    data: 'load_count',
    render: (data, type) => renderInteger(data, type),
  },
  {
    title: 'Load Revenue',
    data: 'load_revenue',
    render: (data, type) => renderCurrency(data, type),
  },
  {
    title: 'Recent Job',
    data: 'recent_job',
    render: (data) => renderText(data),
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
  responsive: false,
  fixedHeader: true,
  scrollX: true,
  autoWidth: false,
  order: [[0, 'asc']],
  layout: {
    topStart: 'pageLength',
    topEnd: 'search',
    bottomStart: 'info',
    bottomEnd: 'paging',
  },
  columnDefs: [
    { targets: '_all', className: 'dt-center' },
    { targets: 1, className: 'dt-center dt-driver-history' },
  ],
}

function handleTableClick(event) {
  const trailerLink = event.target.closest('.js-trailer-link')
  if (trailerLink) {
    event.preventDefault()
    const row = parseJson(trailerLink.dataset.row || '')
    if (row) {
      void openVehicleEditor(row)
    }
    return
  }

  const driverLink = event.target.closest('.js-driver-link')
  if (driverLink) {
    event.preventDefault()
    const entry = parseJson(driverLink.dataset.entry || '')
    if (entry) {
      void openDriverEditor(entry)
    }
  }
}

function resetSummary() {
  summary.value = {
    total_trailers: 0,
    total_load_count: 0,
    total_load_revenue: 0,
    trailers_with_driver_history: 0,
  }
}

function clearFilters() {
  selectedOwner.value = ''
  selectedStartDate.value = defaultStartDate()
  selectedEndDate.value = todayDateString()
  rows.value = []
  err.value = ''
  resetSummary()
  closeVehicleEditor()
  closeDriverEditor()
  tableKey.value += 1
}

async function loadOwners() {
  const data = await fetchOwners()
  owners.value = data.owners || []
}

async function loadReport() {
  err.value = ''

  if (selectedStartDate.value && selectedEndDate.value && selectedStartDate.value > selectedEndDate.value) {
    err.value = 'Start date cannot be after end date.'
    rows.value = []
    resetSummary()
    tableKey.value += 1
    return
  }

  loading.value = true

  try {
    if (!selectedOwner.value) {
      rows.value = []
      resetSummary()
      tableKey.value += 1
      return
    }

    const data = await fetchTrailerTracking({
      owner: selectedOwner.value,
      start_date: selectedStartDate.value,
      end_date: selectedEndDate.value,
    })

    rows.value = data.rows || []
    summary.value = data.summary || {
      total_trailers: 0,
      total_load_count: 0,
      total_load_revenue: 0,
      trailers_with_driver_history: 0,
    }
    tableKey.value += 1
  } catch (error) {
    rows.value = []
    resetSummary()
    err.value = error?.message || 'Failed to load trailer tracking report.'
    tableKey.value += 1
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  if (tableWrap.value) {
    tableWrap.value.addEventListener('click', handleTableClick)
  }

  try {
    await loadOwners()
  } catch (error) {
    err.value = error?.message || 'Failed to load owners.'
  }
})

onBeforeUnmount(() => {
  if (tableWrap.value) {
    tableWrap.value.removeEventListener('click', handleTableClick)
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
  background: #f3f6fb;
  color: #0f172a;
}

.page-wrap {
  min-height: 100vh;
  padding: 4px;
}

.page-inner {
  width: 100%;
  max-width: none;
  margin: 0 auto;
}

.panel {
  background: #ffffff;
  border: 1px solid #dbe2ea;
  border-radius: 6px;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}

.top-panel {
  padding: 6px 8px;
  margin-bottom: 6px;
}

.top-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 6px;
}

.title-block {
  min-width: 220px;
}

.page-title {
  margin: 0;
  font-size: 15px;
  line-height: 1.05;
  font-weight: 700;
  color: #0f172a;
}

.page-subtitle {
  margin-top: 1px;
  font-size: 10px;
  color: #64748b;
}

.search-actions {
  display: grid;
  grid-template-columns: minmax(130px, 190px) 122px 122px auto auto;
  gap: 4px;
  align-items: center;
}

.search-input {
  height: 30px;
  border: 1px solid #cbd5e1;
  border-radius: 5px;
  padding: 0 7px;
  font-size: 12px;
  background: #fff;
  color: #0f172a;
}

.search-input:focus {
  outline: none;
  border-color: #0f172a;
  box-shadow: 0 0 0 2px rgba(15, 23, 42, 0.06);
}

.btn {
  height: 30px;
  border-radius: 5px;
  border: 1px solid transparent;
  padding: 0 9px;
  font-size: 11px;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
}

.btn:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.btn-dark {
  background: #0f172a;
  color: #fff;
}

.btn-light {
  background: #fff;
  color: #111827;
  border-color: #cbd5e1;
}

.chip-row {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  margin-top: 4px;
}

.meta-pill {
  display: inline-flex;
  align-items: center;
  padding: 3px 7px;
  border-radius: 999px;
  background: #f8fafc;
  border: 1px solid #dbe2ea;
  font-size: 10px;
  font-weight: 700;
  color: #334155;
}

.summary-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 6px;
  margin-bottom: 6px;
}

.summary-card {
  padding: 6px 8px;
}

.summary-label {
  font-size: 9px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #64748b;
  margin-bottom: 2px;
  font-weight: 700;
}

.summary-value {
  font-size: 15px;
  line-height: 1;
  font-weight: 700;
  color: #0f172a;
}

.error-box {
  margin-bottom: 6px;
  padding: 7px 8px;
  border-color: #fecaca;
  background: #fef2f2;
  color: #991b1b;
  font-size: 11px;
  font-weight: 600;
}

.table-panel {
  padding: 2px 4px !important;
}

.report-table {
  width: 100% !important;
}

.report-link {
  color: #1d4ed8;
  text-decoration: none;
  font-weight: 600;
}

.report-link:hover {
  text-decoration: underline;
}

.driver-history-link,
.driver-history-text {
  display: inline-block;
  margin: 0;
  white-space: normal;
}

.badge-missing {
  display: inline-flex;
  align-items: center;
  padding: 1px 5px;
  border-radius: 999px;
  background: #f8fafc;
  border: 1px solid #cbd5e1;
  color: #64748b;
  font-size: 9px;
  font-weight: 700;
}

table.dataTable {
  width: 100% !important;
  margin: 0 !important;
}

table.dataTable thead th {
  padding: 3px 5px !important;
  font-size: 10px;
  font-weight: 700;
  color: #475569;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0 !important;
  line-height: 1;
  text-align: center !important;
}

table.dataTable tbody td {
  padding: 3px 5px !important;
  font-size: 12px;
  line-height: 1.2;
  border-bottom: 1px solid #e2e8f0 !important;
  vertical-align: top;
  text-align: center !important;
}

table.dataTable.compact thead th {
  padding-top: 2px !important;
  padding-bottom: 2px !important;
}

table.dataTable.compact tbody td {
  padding-top: 2px !important;
  padding-bottom: 2px !important;
}

table.dataTable td.dt-driver-history {
  white-space: normal !important;
  min-width: 220px;
  text-align: center !important;
}

div.dataTables_wrapper,
div.dt-container {
  font-size: 12px;
  margin: 0 !important;
}

div.dt-container .dt-layout-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  width: 100%;
  margin: 0;
}

div.dt-container .dt-layout-row:not(:last-child) {
  margin-bottom: 6px;
}

div.dt-container .dt-layout-cell {
  display: flex;
  align-items: center;
  min-width: 0;
}

div.dt-container .dt-layout-end {
  margin-left: auto;
  justify-content: flex-end;
}

div.dt-container .dt-search,
div.dt-container .dt-length,
div.dt-container .dt-info,
div.dt-container .dt-paging {
  float: none !important;
  clear: none !important;
  margin: 0 !important;
}

div.dt-container .dt-search label,
div.dt-container .dt-length label {
  display: inline-flex !important;
  align-items: center !important;
  gap: 6px !important;
  margin: 0 !important;
  white-space: nowrap !important;
  font-size: 12px !important;
  line-height: 1 !important;
}

div.dt-container .dt-search input,
div.dt-container .dt-length select {
  border: 1px solid #cbd5e1;
  border-radius: 4px;
  background: #fff;
  height: 24px !important;
  min-height: 24px !important;
  padding: 0 6px !important;
  font-size: 12px !important;
  margin: 0 !important;
  box-sizing: border-box;
}

div.dt-container .dt-search input {
  width: 160px;
}

div.dt-container .dt-length select {
  width: 58px;
}

div.dt-container .dt-paging nav {
  display: inline-flex !important;
  align-items: center !important;
  flex-wrap: nowrap !important;
  gap: 4px !important;
  white-space: nowrap !important;
}

div.dt-container .dt-paging .dt-paging-button {
  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;
  min-width: 30px !important;
  height: 28px !important;
  box-sizing: border-box;
  border-radius: 4px !important;
  border: 1px solid #cbd5e1 !important;
  background: #fff !important;
  color: #334155 !important;
  padding: 0 8px !important;
  font-size: 12px !important;
  line-height: 1 !important;
  margin: 0 !important;
  white-space: nowrap !important;
}

div.dt-container .dt-paging .dt-paging-button.current {
  background: #e5e7eb !important;
  border-color: #cbd5e1 !important;
  color: #111827 !important;
}

div.dt-container .dt-paging .dt-paging-button:hover {
  background: #f3f4f6 !important;
  border-color: #cbd5e1 !important;
  color: #111827 !important;
}

div.dt-container .dt-paging .dt-paging-button.disabled {
  opacity: 0.5 !important;
  cursor: not-allowed !important;
}

div.dataTables_scrollBody {
  border-bottom: 0 !important;
}

@media (max-width: 1200px) {
  .summary-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .search-actions {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 900px) {
  .page-wrap {
    padding: 5px;
  }

  .top-row {
    flex-direction: column;
  }

  .summary-grid {
    grid-template-columns: 1fr;
  }

  .search-actions {
    grid-template-columns: 1fr;
    width: 100%;
  }

  div.dt-container .dt-layout-row {
    display: block;
  }

  div.dt-container .dt-layout-end {
    margin-left: 0;
    justify-content: flex-start;
  }

  div.dt-container .dt-search,
  div.dt-container .dt-length,
  div.dt-container .dt-info,
  div.dt-container .dt-paging {
    display: block !important;
    text-align: left !important;
    margin-top: 4px !important;
  }

  div.dt-container .dt-paging {
    overflow-x: auto;
  }
}
</style>