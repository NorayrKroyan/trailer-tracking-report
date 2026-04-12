<template>
  <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/40" @click="emit('close')"></div>

    <div class="relative w-full max-w-4xl overflow-hidden rounded-2xl bg-white shadow-xl">
      <div class="flex items-center justify-between border-b border-gray-200 px-5 py-3">
        <div>
          <div class="text-lg font-semibold">Edit Vehicle</div>
          <div class="mt-0.5 text-sm text-gray-500">{{ subtitle || 'Trailer Vehicle' }}</div>
        </div>

        <button
            type="button"
            class="rounded-lg px-2 py-1 text-sm hover:bg-gray-100"
            @click="emit('close')"
        >
          ✕
        </button>
      </div>

      <div class="max-h-[78vh] overflow-y-auto px-5 py-4">
        <div v-if="loading" class="py-10 text-center text-gray-500">Loading...</div>

        <div v-else class="mx-auto w-full space-y-3">
          <div class="bg-white">
            <div class="grid grid-cols-1 gap-3 py-2 lg:grid-cols-2">
              <div class="space-y-2">
                <FieldRow label="Trailer:">
                  <div>
                    <input
                        v-model="form.vehicle_number"
                        type="text"
                        :class="['input', inputErr('vehicle_number')]"
                        placeholder="Trailer"
                    />
                    <ErrText :msg="errors.vehicle_number" />
                  </div>
                </FieldRow>

                <FieldRow label="Vehicle Name:">
                  <div>
                    <input
                        v-model="form.vehicle_name"
                        type="text"
                        :class="['input', inputErr('vehicle_name')]"
                        placeholder="Vehicle Name"
                    />
                    <ErrText :msg="errors.vehicle_name" />
                  </div>
                </FieldRow>
              </div>

              <div class="space-y-2">
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

                <FieldRow label="License Plate:">
                  <div>
                    <input
                        v-model="form.license_plate"
                        type="text"
                        :class="['input', inputErr('license_plate')]"
                        placeholder="License Plate"
                    />
                    <ErrText :msg="errors.license_plate" />
                  </div>
                </FieldRow>
              </div>
            </div>

            <div class="mt-4 border-b border-gray-200">
              <nav class="-mb-px flex items-center gap-6">
                <button
                    type="button"
                    class="whitespace-nowrap border-b-2 px-1 pb-2 text-sm font-medium"
                    :class="tabClass('vehicle')"
                    @click="activeTab = 'vehicle'"
                >
                  Vehicle
                </button>

                <button
                    type="button"
                    class="whitespace-nowrap border-b-2 px-1 pb-2 text-sm font-medium"
                    :class="tabClass('report')"
                    @click="activeTab = 'report'"
                >
                  Trailer Report Snapshot
                </button>

                <button
                    type="button"
                    class="whitespace-nowrap border-b-2 px-1 pb-2 text-sm font-medium"
                    :class="tabClass('history')"
                    @click="activeTab = 'history'"
                >
                  Driver History
                </button>
              </nav>
            </div>

            <div v-if="activeTab === 'vehicle'" class="mt-4 rounded-xl bg-white">
              <div class="grid grid-cols-1 gap-3 py-2 lg:grid-cols-2">
                <div class="space-y-2">
                  <FieldRow label="Trailer:">
                    <div>
                      <input
                          v-model="form.vehicle_number"
                          type="text"
                          :class="['input', inputErr('vehicle_number')]"
                          placeholder="Trailer"
                      />
                      <ErrText :msg="errors.vehicle_number" />
                    </div>
                  </FieldRow>

                  <FieldRow label="Vehicle Name:">
                    <div>
                      <input
                          v-model="form.vehicle_name"
                          type="text"
                          :class="['input', inputErr('vehicle_name')]"
                          placeholder="Vehicle Name"
                      />
                      <ErrText :msg="errors.vehicle_name" />
                    </div>
                  </FieldRow>
                </div>

                <div class="space-y-2">
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

                  <FieldRow label="License Plate:">
                    <div>
                      <input
                          v-model="form.license_plate"
                          type="text"
                          :class="['input', inputErr('license_plate')]"
                          placeholder="License Plate"
                      />
                      <ErrText :msg="errors.license_plate" />
                    </div>
                  </FieldRow>
                </div>
              </div>
            </div>

            <div v-else-if="activeTab === 'report'" class="mt-4 rounded-xl border border-gray-200 bg-white">
              <div class="rounded-t-xl bg-gray-50 px-4 py-2 text-sm font-semibold text-gray-800">
                Trailer Report Snapshot
              </div>

              <div class="grid grid-cols-1 gap-3 px-4 py-3 lg:grid-cols-2">
                <div class="space-y-2">
                  <FieldRow label="Load Count:">
                    <div class="input input-static">
                      {{ reportMeta.load_count ?? 0 }}
                    </div>
                  </FieldRow>

                  <FieldRow label="Load Revenue:">
                    <div class="input input-static">
                      {{ formatCurrency(reportMeta.load_revenue ?? 0) }}
                    </div>
                  </FieldRow>
                </div>

                <div class="space-y-2">
                  <FieldRow label="Recent Job:">
                    <div class="input input-static">
                      {{ reportMeta.recent_job || '—' }}
                    </div>
                  </FieldRow>
                </div>
              </div>
            </div>

            <div v-else-if="activeTab === 'history'" class="mt-4 rounded-xl border border-gray-200 bg-white">
              <div class="rounded-t-xl bg-gray-50 px-4 py-2 text-sm font-semibold text-gray-800">
                Driver History
              </div>

              <div v-if="historyLines.length" class="overflow-hidden rounded-b-xl">
                <table class="min-w-full text-sm">
                  <thead class="bg-gray-50 text-gray-700">
                  <tr class="bg-gray-200">
                    <th class="px-3 py-2 text-left font-medium">History</th>
                  </tr>
                  </thead>
                  <tbody class="divide-y bg-white">
                  <tr v-for="(line, index) in historyLines" :key="`vehicle-history-${index}`">
                    <td class="px-3 py-2 text-left text-sm text-gray-800">
                      {{ line }}
                    </td>
                  </tr>
                  </tbody>
                </table>
              </div>

              <div v-else class="p-4 text-sm text-gray-500">
                No driver history
              </div>
            </div>

            <div
                v-if="formError"
                class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
            >
              {{ formError }}
            </div>
          </div>
        </div>
      </div>

      <div class="flex items-center justify-between gap-3 border-t border-gray-200 px-5 py-3">
        <div></div>

        <div class="flex items-center justify-end gap-3">
          <button
              type="button"
              class="rounded-xl border border-gray-300 bg-white px-6 py-3 text-sm hover:bg-gray-50 disabled:opacity-50"
              :disabled="saving"
              @click="emit('close')"
          >
            Cancel
          </button>

          <button
              type="button"
              class="rounded-xl bg-gray-900 px-8 py-3 text-sm font-semibold text-white hover:bg-gray-800 disabled:opacity-50"
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
import { defineComponent, h, ref, watch } from 'vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
  saving: { type: Boolean, default: false },
  subtitle: { type: String, default: '' },
  form: { type: Object, required: true },
  errors: { type: Object, default: () => ({}) },
  formError: { type: String, default: '' },
  historyLines: { type: Array, default: () => [] },
  reportMeta: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['close', 'save'])

const activeTab = ref('vehicle')

watch(
    () => props.open,
    (open) => {
      if (open) {
        activeTab.value = 'vehicle'
      }
    }
)

function tabClass(tab) {
  const active = activeTab.value === tab

  return active
      ? 'border-gray-900 text-gray-900'
      : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
}

function inputErr(key) {
  return props.errors?.[key] ? 'input-error' : ''
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

const FieldRow = defineComponent({
  name: 'FieldRow',
  props: {
    label: { type: String, required: true },
  },
  setup(fieldProps, { slots }) {
    return () =>
        h('div', { class: 'grid grid-cols-[170px_minmax(0,1fr)] items-center gap-3' }, [
          h(
              'div',
              { class: 'pr-1 text-right text-sm font-semibold text-gray-800 whitespace-nowrap' },
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
.input {
  width: 100%;
  height: 48px;
  border: 1px solid #d1d5db;
  border-radius: 0.75rem;
  padding: 0 1rem;
  font-size: 0.95rem;
  outline: none;
  background: #ffffff;
}

.input:focus {
  border-color: #3b82f6;
  box-shadow: 0 0 0 1px #3b82f6;
}

.input-static {
  display: flex;
  align-items: center;
  color: #1f2937;
  background: #ffffff;
}

.input-error {
  border-color: #ef4444 !important;
  box-shadow: 0 0 0 1px #ef4444 !important;
}
</style>