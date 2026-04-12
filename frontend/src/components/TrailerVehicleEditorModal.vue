<template>
  <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/40" @click="emit('close')"></div>

    <div class="vehicle-modal relative flex max-h-[94vh] w-full max-w-[1320px] flex-col overflow-hidden bg-white">
      <div class="border-b border-gray-200 px-7 py-6">
        <div class="text-[18px] font-semibold text-gray-900">Edit Vehicle</div>
      </div>

      <div class="flex-1 overflow-y-auto px-8 py-8">
        <div v-if="loading" class="py-16 text-center text-sm text-gray-500">Loading...</div>

        <div v-else class="space-y-8">
          <div class="grid grid-cols-1 gap-x-20 gap-y-3 xl:grid-cols-2">
            <div class="space-y-3">
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
            </div>

            <div class="space-y-3">
              <FieldRow label="License Plate:">
                <div>
                  <input
                      v-model="form.vehicle_number"
                      type="text"
                      :class="['input', inputErr('vehicle_number')]"
                      placeholder="Plate"
                  />
                  <ErrText :msg="errors.vehicle_number" />
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
            </div>
          </div>

          <div class="border-b border-gray-200">
            <nav class="-mb-px flex flex-wrap items-end gap-8">
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

          <div v-if="activeTab === 'assignment'" class="overflow-hidden rounded-[22px] border border-gray-200 bg-white">
            <table class="min-w-full text-sm">
              <thead class="bg-[#eef0f3] text-gray-700">
              <tr>
                <th class="px-6 py-4 text-left font-medium">Date</th>
                <th class="px-6 py-4 text-left font-medium">Action</th>
                <th class="px-6 py-4 text-left font-medium">Driver</th>
                <th class="px-6 py-4 text-left font-medium">Current</th>
              </tr>
              </thead>

              <tbody v-if="normalizedAssignmentHistory.length" class="divide-y divide-gray-100 bg-white">
              <tr
                  v-for="(row, index) in normalizedAssignmentHistory"
                  :key="`assignment-${index}`"
              >
                <td class="px-6 py-4 text-gray-800">{{ row.date || '—' }}</td>
                <td class="px-6 py-4 text-gray-800">{{ row.action || 'Assigned' }}</td>
                <td class="px-6 py-4 text-gray-800">{{ row.driver || '—' }}</td>
                <td class="px-6 py-4">
                    <span
                        v-if="row.current"
                        class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-emerald-700"
                    >
                      Current
                    </span>
                  <span v-else class="text-gray-400">—</span>
                </td>
              </tr>
              </tbody>

              <tbody v-else class="bg-white">
              <tr>
                <td colspan="4" class="px-6 py-6 text-sm text-gray-500">
                  No assignment history
                </td>
              </tr>
              </tbody>
            </table>
          </div>

          <div v-else-if="activeTab === 'documents'" class="overflow-hidden rounded-[22px] border border-gray-200 bg-white">
            <table class="min-w-full text-sm">
              <thead class="bg-[#eef0f3] text-gray-700">
              <tr>
                <th class="px-6 py-4 text-left font-medium">Name</th>
                <th class="px-6 py-4 text-left font-medium">Type</th>
                <th class="px-6 py-4 text-left font-medium">Uploaded</th>
              </tr>
              </thead>

              <tbody class="bg-white">
              <tr>
                <td colspan="3" class="px-6 py-6 text-sm text-gray-500">
                  No documents
                </td>
              </tr>
              </tbody>
            </table>
          </div>

          <div
              v-if="formError"
              class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
          >
            {{ formError }}
          </div>
        </div>
      </div>

      <div class="flex items-center justify-end gap-4 border-t border-gray-200 px-7 py-7">
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

const emit = defineEmits(['close', 'save'])

const activeTab = ref('assignment')

watch(
    () => props.open,
    (open) => {
      if (open) {
        activeTab.value = 'assignment'
      }
    },
    { immediate: true }
)

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
        h('div', { class: 'grid grid-cols-[220px_minmax(0,1fr)] items-center gap-5' }, [
          h(
              'div',
              { class: 'text-right text-[18px] font-semibold leading-6 text-gray-700 whitespace-nowrap' },
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
            ? h('div', { class: 'mt-1.5 text-xs font-medium text-red-600' }, errProps.msg)
            : null
  },
})
</script>

<style scoped>
.vehicle-modal {
  border-radius: 28px;
  box-shadow:
      0 24px 60px rgba(15, 23, 42, 0.18),
      0 8px 24px rgba(15, 23, 42, 0.1);
}

.input {
  width: 100%;
  height: 58px;
  border: 1px solid #d9dce3;
  border-radius: 18px;
  padding: 0 20px;
  background: #fff;
  font-size: 16px;
  color: #1f2937;
  outline: none;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.input::placeholder {
  color: #9ca3af;
}

.input:focus {
  border-color: #94a3b8;
  box-shadow: 0 0 0 1px #94a3b8;
}

.input-error {
  border-color: #ef4444 !important;
  box-shadow: 0 0 0 1px #ef4444 !important;
}

.tab-button {
  border-bottom-width: 3px;
  padding: 0 2px 12px;
  font-size: 15px;
  font-weight: 500;
  white-space: nowrap;
}

.future-text {
  color: #ef4444;
}

.btn-secondary,
.btn-primary {
  height: 60px;
  border-radius: 18px;
  padding: 0 30px;
  font-size: 16px;
  font-weight: 600;
  transition: all 0.15s ease;
}

.btn-secondary {
  border: 1px solid #d1d5db;
  background: #fff;
  color: #374151;
}

.btn-secondary:hover:not(:disabled) {
  background: #f9fafb;
}

.btn-primary {
  border: 1px solid #111827;
  background: #0f172a;
  color: #fff;
  min-width: 126px;
}

.btn-primary:hover:not(:disabled) {
  background: #111827;
}

.btn-secondary:disabled,
.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

@media (max-width: 768px) {
  :deep(.grid-cols-\[220px_minmax\(0\,1fr\)\]) {
    grid-template-columns: 1fr;
    gap: 8px;
  }

  :deep(.grid-cols-\[220px_minmax\(0\,1fr\)\] > div:first-child) {
    text-align: left !important;
  }

  .btn-secondary,
  .btn-primary {
    height: 52px;
    padding: 0 22px;
    font-size: 15px;
  }
}
</style>
