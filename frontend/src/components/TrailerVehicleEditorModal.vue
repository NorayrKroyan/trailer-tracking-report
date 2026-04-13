<template>
  <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/40" @click="emit('close')"></div>

    <div class="vehicle-modal relative w-full max-w-5xl overflow-hidden rounded-2xl bg-white shadow-xl">
      <div class="border-b border-gray-200 px-5 py-3">
        <div class="text-lg font-semibold text-gray-900">Edit Vehicle</div>
      </div>

      <div class="max-h-[75vh] overflow-y-auto px-5 py-4">
        <div v-if="loading" class="py-10 text-center text-gray-500">Loading...</div>

        <div v-else class="space-y-3">
          <div class="grid grid-cols-1 gap-y-2 md:grid-cols-2 md:gap-x-6">
            <div class="space-y-2">
              <FieldRow label="Vehicle Type:">
                <div>
                  <div class="select-wrap">
                    <select
                        v-model="form.vehicle_type"
                        :class="['select-input', inputErr('vehicle_type')]"
                    >
                      <option value="">Select type</option>
                      <option v-for="option in vehicleTypeOptions" :key="option" :value="option">
                        {{ option }}
                      </option>
                    </select>
                    <span class="select-caret">⌄</span>
                  </div>
                  <ErrText :msg="errors.vehicle_type" />
                </div>
              </FieldRow>

              <FieldRow label="VIN:">
                <div>
                  <input
                      v-model="form.vin"
                      type="text"
                      :class="['input', inputErr('vin')]"
                      placeholder="VIN"
                  />
                  <ErrText :msg="errors.vin" />
                </div>
              </FieldRow>

              <FieldRow label="Year:">
                <div>
                  <input
                      v-model="form.year"
                      type="text"
                      :class="['input', inputErr('year')]"
                      placeholder="Year"
                  />
                  <ErrText :msg="errors.year" />
                </div>
              </FieldRow>

              <FieldRow label="Make:">
                <div>
                  <div class="select-wrap">
                    <select
                        v-model="form.make"
                        :class="['select-input', inputErr('make')]"
                    >
                      <option value="">Select make</option>
                      <option v-for="option in makeOptions" :key="option" :value="option">
                        {{ option }}
                      </option>
                    </select>
                    <span class="select-caret">⌄</span>
                  </div>
                  <ErrText :msg="errors.make" />
                </div>
              </FieldRow>

              <FieldRow label="Model:">
                <div>
                  <div class="select-wrap">
                    <select
                        v-model="form.model"
                        :class="['select-input', inputErr('model')]"
                    >
                      <option value="">Select model</option>
                      <option v-for="option in modelOptions" :key="option" :value="option">
                        {{ option }}
                      </option>
                    </select>
                    <span class="select-caret">⌄</span>
                  </div>
                  <ErrText :msg="errors.model" />
                </div>
              </FieldRow>

              <FieldRow label="Color:">
                <div>
                  <div class="select-wrap">
                    <select
                        v-model="form.color"
                        :class="['select-input', inputErr('color')]"
                    >
                      <option value="">Select color</option>
                      <option v-for="option in colorOptions" :key="option" :value="option">
                        {{ option }}
                      </option>
                    </select>
                    <span class="select-caret">⌄</span>
                  </div>
                  <ErrText :msg="errors.color" />
                </div>
              </FieldRow>
            </div>

            <div class="space-y-2">
              <FieldRow label="Trailer Number:">
                <div>
                  <input
                      v-model="form.vehicle_name"
                      type="text"
                      :class="['input', inputErr('vehicle_name')]"
                      placeholder="Trailer Number"
                  />
                  <ErrText :msg="errors.vehicle_name" />
                </div>
              </FieldRow>

              <FieldRow label="License Plate:">
                <div>
                  <input
                      v-model="form.vehicle_number"
                      type="text"
                      :class="['input', inputErr('vehicle_number')]"
                      placeholder="License Plate"
                  />
                  <ErrText :msg="errors.vehicle_number" />
                </div>
              </FieldRow>

              <FieldRow label="Registration State:">
                <div>
                  <div class="select-wrap">
                    <select
                        v-model="form.registration_state"
                        :class="['select-input', inputErr('registration_state')]"
                    >
                      <option value="">Select state</option>
                      <option v-for="option in stateOptions" :key="option" :value="option">
                        {{ option }}
                      </option>
                    </select>
                    <span class="select-caret">⌄</span>
                  </div>
                  <ErrText :msg="errors.registration_state" />
                </div>
              </FieldRow>

              <FieldRow label="Owner:">
                <div>
                  <input
                      v-model="form.owner"
                      type="text"
                      :class="['input', inputErr('owner')]"
                      placeholder="Owner"
                  />
                  <ErrText :msg="errors.owner" />
                </div>
              </FieldRow>

              <FieldRow label="Insurance Provided By:">
                <div>
                  <div class="select-wrap">
                    <select
                        v-model="form.insurance_provider"
                        :class="['select-input', inputErr('insurance_provider')]"
                    >
                      <option value="">Select provider</option>
                      <option v-for="option in insuranceOptions" :key="option" :value="option">
                        {{ option }}
                      </option>
                    </select>
                    <span class="select-caret">⌄</span>
                  </div>
                  <ErrText :msg="errors.insurance_provider" />
                </div>
              </FieldRow>

              <FieldRow label="System Plate:">
                <div>
                  <input
                      v-model="form.license_plate"
                      type="text"
                      :class="['input', inputErr('license_plate')]"
                      placeholder="System Plate"
                  />
                  <ErrText :msg="errors.license_plate" />
                </div>
              </FieldRow>

              <FieldRow label="Process Rental and Billing:">
                <div class="flex min-h-[38px] items-center">
                  <label class="inline-flex items-center">
                    <input
                        v-model="form.process_rental_and_billing"
                        type="checkbox"
                        class="billing-checkbox"
                    />
                  </label>
                </div>
              </FieldRow>
            </div>
          </div>

          <div class="border-b border-gray-200">
            <nav class="-mb-px flex flex-wrap items-end gap-6">
              <button
                  type="button"
                  class="tab-button"
                  :class="tabClass('assignment')"
                  @click="activeTab = 'assignment'"
              >
                Assignment History
              </button>

              <button
                  type="button"
                  class="tab-button"
                  :class="tabClass('documents')"
                  @click="activeTab = 'documents'"
              >
                Documents
              </button>

              <button
                  type="button"
                  class="tab-button text-gray-300"
                  disabled
              >
                Rental
              </button>

              <button
                  type="button"
                  class="tab-button text-gray-300"
                  disabled
              >
                Billing History <span class="future-text">(future)</span>
              </button>

              <button
                  type="button"
                  class="tab-button text-gray-300"
                  disabled
              >
                Load History <span class="future-text">(future)</span>
              </button>
            </nav>
          </div>

          <div
              v-if="activeTab === 'assignment'"
              class="overflow-hidden rounded-xl border border-gray-200 bg-white"
          >
            <table class="min-w-full text-sm">
              <thead class="bg-[#eef0f3] text-gray-700">
              <tr>
                <th class="px-5 py-3 text-left text-sm font-semibold">Date</th>
                <th class="px-5 py-3 text-left text-sm font-semibold">Action</th>
                <th class="px-5 py-3 text-left text-sm font-semibold">Driver</th>
                <th class="px-5 py-3 text-left text-sm font-semibold">Current</th>
              </tr>
              </thead>

              <tbody v-if="normalizedAssignmentHistory.length" class="divide-y divide-gray-100 bg-white">
              <tr
                  v-for="(row, index) in normalizedAssignmentHistory"
                  :key="`assignment-${index}`"
              >
                <td class="px-5 py-3 text-sm text-gray-800">{{ row.date || '—' }}</td>
                <td class="px-5 py-3 text-sm text-gray-800">{{ row.action || '—' }}</td>
                <td class="px-5 py-3 text-sm text-gray-800">{{ row.driver || '—' }}</td>
                <td class="px-5 py-3 text-sm text-gray-800">
                  <span v-if="row.current">Yes</span>
                  <span v-else>—</span>
                </td>
              </tr>
              </tbody>

              <tbody v-else class="bg-white">
              <tr>
                <td colspan="4" class="px-5 py-4 text-sm text-gray-500">
                  No records
                </td>
              </tr>
              </tbody>
            </table>
          </div>

          <div
              v-else-if="activeTab === 'documents'"
              class="overflow-hidden rounded-xl border border-gray-200 bg-white"
          >
            <table class="min-w-full text-sm">
              <thead class="bg-[#eef0f3] text-gray-700">
              <tr>
                <th class="px-5 py-3 text-left text-sm font-semibold">Name</th>
                <th class="px-5 py-3 text-left text-sm font-semibold">Type</th>
                <th class="px-5 py-3 text-left text-sm font-semibold">Uploaded</th>
              </tr>
              </thead>

              <tbody class="bg-white">
              <tr>
                <td colspan="3" class="px-5 py-4 text-sm text-gray-500">
                  No records
                </td>
              </tr>
              </tbody>
            </table>
          </div>

          <div
              v-if="formError"
              class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
          >
            {{ formError }}
          </div>
        </div>
      </div>

      <div class="flex items-center justify-between gap-2 border-t border-gray-200 px-5 py-3">
        <button
            type="button"
            class="btn-delete"
            :disabled="saving"
            @click="emit('delete')"
        >
          Delete
        </button>

        <div class="flex items-center gap-2">
          <button
              type="button"
              class="btn-secondary"
              :disabled="saving"
              @click="emit('close')"
          >
            Cancel
          </button>

          <button
              type="button"
              class="btn-primary"
              :disabled="saving"
              @click="emit('save')"
          >
            {{ saving ? 'Saving...' : 'Save' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, defineComponent, h, ref, watch } from 'vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
  saving: { type: Boolean, default: false },
  subtitle: { type: String, default: '' },
  form: { type: Object, required: true },
  errors: { type: Object, default: () => ({}) },
  formError: { type: String, default: '' },
  assignmentHistory: { type: Array, default: () => [] },
  historyLines: { type: Array, default: () => [] },
  reportMeta: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['close', 'save', 'delete'])

const form = props.form
const activeTab = ref('assignment')

const vehicleTypeOptions = ['Trailer', 'Truck']
const makeOptions = [
  'Freightliner',
  'Kenworth',
  'Peterbilt',
  'Volvo',
  'International',
  'Mack',
  'Utility',
  'Wabash',
  'Great Dane',
]
const modelOptions = [
  'Day Cab',
  'Sleeper',
  'Dry Van',
  'Reefer',
  'Flatbed',
  'Step Deck',
  'Lowboy',
]
const colorOptions = [
  'White',
  'Black',
  'Blue',
  'Red',
  'Gray',
  'Silver',
  'Green',
  'Yellow',
]
const insuranceOptions = [
  'Rental Agency',
  'Carrier',
  'Owner',
  'Company',
]
const stateOptions = [
  'AL','AK','AZ','AR','CA','CO','CT','DE','FL','GA',
  'HI','ID','IL','IN','IA','KS','KY','LA','ME','MD',
  'MA','MI','MN','MS','MO','MT','NE','NV','NH','NJ',
  'NM','NY','NC','ND','OH','OK','OR','PA','RI','SC',
  'SD','TN','TX','UT','VT','VA','WA','WV','WI','WY',
]

const normalizedAssignmentHistory = computed(() => {
  if (Array.isArray(props.assignmentHistory) && props.assignmentHistory.length) {
    return props.assignmentHistory
  }

  if (Array.isArray(props.historyLines) && props.historyLines.length) {
    return props.historyLines.map((line) => ({
      date: '',
      action: String(line ?? ''),
      driver: '',
      current: false,
    }))
  }

  return []
})

watch(
    () => props.open,
    (value) => {
      if (!value) return

      activeTab.value = 'assignment'
      ensureFormFields()
    },
    { immediate: true }
)

function ensureFormFields() {
  const defaults = {
    vehicle_type: 'Trailer',
    vin: '',
    year: '',
    make: '',
    model: '',
    color: '',
    registration_state: '',
    insurance_provider: 'Rental Agency',
    process_rental_and_billing: false,
  }

  Object.entries(defaults).forEach(([key, defaultValue]) => {
    if (form[key] === undefined || form[key] === null) {
      form[key] = defaultValue
    }
  })
}

function tabClass(tab) {
  return activeTab.value === tab
      ? 'border-gray-900 text-gray-900'
      : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'
}

function inputErr(key) {
  return props.errors?.[key] ? 'input-error' : ''
}

const FieldRow = defineComponent({
  name: 'FieldRow',
  props: {
    label: { type: String, required: true },
  },
  setup(fieldProps, { slots }) {
    return () =>
        h('div', { class: 'grid grid-cols-[160px_minmax(0,1fr)] items-center gap-2' }, [
          h(
              'div',
              { class: 'text-right pr-1 text-sm font-semibold text-gray-800 whitespace-nowrap' },
              fieldProps.label
          ),
          h('div', { class: 'min-w-0' }, slots.default ? slots.default() : null),
        ])
  },
})

const ErrText = defineComponent({
  name: 'ErrText',
  props: {
    msg: { type: String, default: '' },
  },
  setup(errProps) {
    return () =>
        errProps.msg
            ? h('div', { class: 'mt-1 text-xs font-medium text-red-600' }, errProps.msg)
            : null
  },
})
</script>

<style scoped>
.vehicle-modal {
  box-shadow: 0 20px 50px rgba(15, 23, 42, 0.18);
}

.input,
.select-input {
  width: 100%;
  border: 1px solid #d1d5db;
  border-radius: 0.5rem;
  background: #ffffff;
  padding: 0.5rem 0.75rem;
  font-size: 0.875rem;
  line-height: 1.25rem;
  color: #111827;
  outline: none;
}

.textarea-input {
  width: 100%;
  resize: none;
  border: 1px solid #d1d5db;
  border-radius: 0.5rem;
  background: #ffffff;
  padding: 0.5rem 0.75rem;
  font-size: 0.875rem;
  line-height: 1.25rem;
  color: #111827;
  outline: none;
}

.input::placeholder,
.textarea-input::placeholder {
  color: #9ca3af;
}

.input:focus,
.select-input:focus,
.textarea-input:focus {
  border-color: #9ca3af;
  box-shadow: 0 0 0 2px rgba(243, 244, 246, 1);
}

.select-wrap {
  position: relative;
}

.select-input {
  appearance: none;
  padding-right: 2.25rem;
}

.select-caret {
  position: absolute;
  right: 0.75rem;
  top: 50%;
  transform: translateY(-50%);
  color: #6b7280;
  font-size: 0.95rem;
  pointer-events: none;
}

.input-error {
  border-color: #f87171 !important;
  box-shadow: 0 0 0 2px rgba(254, 226, 226, 1) !important;
}

.billing-checkbox {
  height: 1rem;
  width: 1rem;
  border-radius: 0.25rem;
  border: 1px solid #d1d5db;
  accent-color: #111827;
}

.tab-button {
  border-bottom-width: 2px;
  padding: 0 0 0.6rem;
  font-size: 0.875rem;
  font-weight: 500;
  white-space: nowrap;
}

.future-text {
  color: #ef4444;
}

.close-btn {
  border-radius: 0.5rem;
  padding: 0.25rem 0.5rem;
  font-size: 0.875rem;
  color: #374151;
}

.close-btn:hover {
  background: #f3f4f6;
}

.btn-secondary,
.btn-primary,
.btn-delete {
  border-radius: 0.5rem;
  padding: 0.5rem 1rem;
  font-size: 0.875rem;
  line-height: 1.25rem;
  transition: all 0.15s ease;
}

.btn-secondary {
  border: 1px solid #d1d5db;
  background: #fff;
  color: #111827;
}

.btn-secondary:hover:not(:disabled) {
  background: #f9fafb;
}

.btn-primary {
  border: 1px solid #111827;
  background: #111827;
  color: #fff;
  font-weight: 500;
}

.btn-primary:hover:not(:disabled) {
  background: #1f2937;
}

.btn-delete {
  border: 1px solid #fca5a5;
  background: #fff;
  color: #dc2626;
}

.btn-delete:hover:not(:disabled) {
  background: #fef2f2;
}

.btn-secondary:disabled,
.btn-primary:disabled,
.btn-delete:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

@media (max-width: 768px) {
  :deep(.grid-cols-\[160px_minmax\(0\,1fr\)\]) {
    grid-template-columns: 1fr;
    gap: 0.5rem;
  }

  :deep(.grid-cols-\[160px_minmax\(0\,1fr\)\] > div:first-child) {
    text-align: left !important;
    padding-right: 0 !important;
  }
}
</style>