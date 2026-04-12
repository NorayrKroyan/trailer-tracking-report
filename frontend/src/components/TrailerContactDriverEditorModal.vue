<template>
  <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/40" @click="emit('close')"></div>

    <div class="relative w-full max-w-5xl overflow-hidden rounded-2xl bg-white shadow-xl">
      <div class="flex items-center justify-between border-b border-gray-200 px-5 py-3">
        <div class="text-lg font-semibold">{{ modalTitle }}</div>

        <button
            type="button"
            class="rounded-lg px-2 py-1 text-sm hover:bg-gray-100"
            @click="emit('close')"
        >
          ✕
        </button>
      </div>

      <div class="max-h-[75vh] overflow-y-auto px-5 py-4">
        <div v-if="loading" class="py-10 text-center text-gray-500">Loading...</div>

        <div v-else class="space-y-3">
          <div class="rounded-xl border border-gray-200 p-4">
            <div class="mb-2 text-sm font-semibold text-gray-900">Contact Info</div>

            <div class="grid grid-cols-1 gap-y-2 md:grid-cols-2 md:gap-x-6">
              <FieldRow label="First Name:" class="md:col-span-1">
                <div>
                  <input
                      v-model="form.contact.first_name"
                      type="text"
                      class="w-full rounded-lg border px-3 py-2 text-sm"
                      :class="inputErr('first_name')"
                  />
                  <ErrText :msg="errorMessage('first_name', 'contact.first_name')" />
                </div>
              </FieldRow>

              <FieldRow label="Last Name:" class="md:col-span-1">
                <div>
                  <input
                      v-model="form.contact.last_name"
                      type="text"
                      class="w-full rounded-lg border px-3 py-2 text-sm"
                      :class="inputErr('last_name')"
                  />
                  <ErrText :msg="errorMessage('last_name', 'contact.last_name')" />
                </div>
              </FieldRow>

              <FieldRow label="Phone:" class="md:col-span-1">
                <div>
                  <input
                      v-model="form.contact.phone_number"
                      type="text"
                      class="w-full max-w-[260px] rounded-lg border px-3 py-2 text-sm"
                      :class="inputErr('phone_number')"
                  />
                  <ErrText :msg="errorMessage('phone_number', 'contact.phone_number')" />
                </div>
              </FieldRow>

              <FieldRow label="Email:" class="md:col-span-1">
                <div>
                  <input
                      v-model="form.contact.email"
                      type="email"
                      class="w-full max-w-[360px] rounded-lg border px-3 py-2 text-sm"
                      :class="inputErr('email')"
                  />
                  <ErrText :msg="errorMessage('email', 'contact.email')" />
                </div>
              </FieldRow>

              <FieldRow label="Address:" class="md:col-span-1">
                <div>
                  <textarea
                      v-model="form.contact.address"
                      rows="2"
                      class="w-full resize-none rounded-lg border px-3 py-2 text-sm"
                      :class="inputErr('address')"
                  ></textarea>
                  <ErrText :msg="errorMessage('address', 'contact.address')" />
                </div>
              </FieldRow>

              <FieldRow label="Home State:" class="md:col-span-1">
                <div>
                  <select
                      v-model="form.contact.state"
                      class="w-full max-w-[220px] rounded-lg border bg-white px-3 py-2 text-sm"
                      :class="inputErr('state')"
                  >
                    <option value=""></option>
                    <option
                        v-for="option in stateOptions"
                        :key="`state-${option.value}`"
                        :value="option.value"
                    >
                      {{ option.label }}
                    </option>
                  </select>
                  <ErrText :msg="errorMessage('state', 'contact.state')" />
                </div>
              </FieldRow>

              <div class="hidden md:block md:col-span-1"></div>

              <FieldRow label="Is a Driver:" class="md:col-span-1 -mt-5">
                <div class="w-full max-w-[220px]">
                  <input
                      :checked="driverToggle"
                      type="checkbox"
                      class="h-4 w-4 rounded border-gray-300"
                      @change="emit('update:driverToggle', $event.target.checked)"
                  />
                </div>
              </FieldRow>

              <div class="hidden md:block md:col-span-1"></div>
            </div>
          </div>

          <div v-if="driverToggle" class="rounded-xl border border-gray-200 p-4">
            <div class="mb-2 text-sm font-semibold text-gray-900">Driver Info</div>

            <div class="grid grid-cols-1 gap-y-2 md:grid-cols-2 md:gap-x-6">
              <FieldRow label="Carrier:" class="md:col-span-1">
                <div>
                  <select
                      v-model="form.driver.id_carrier"
                      class="w-full rounded-lg border bg-white px-3 py-2 text-sm"
                      :class="inputErr('id_carrier')"
                  >
                    <option value=""></option>
                    <option
                        v-for="option in carrierOptions"
                        :key="`carrier-${option.value}`"
                        :value="option.value"
                    >
                      {{ option.label }}
                    </option>
                  </select>
                  <ErrText :msg="errorMessage('id_carrier', 'driver.id_carrier')" />
                </div>
              </FieldRow>

              <FieldRow label="Project:" class="md:col-span-1">
                <div>
                  <select
                      v-model="form.driver.idprojects"
                      class="w-full rounded-lg border bg-white px-3 py-2 text-sm"
                      :class="inputErr('idprojects')"
                  >
                    <option value=""></option>
                    <option
                        v-for="option in projectOptions"
                        :key="`project-${option.value}`"
                        :value="option.value"
                    >
                      {{ option.label }}
                    </option>
                  </select>
                  <ErrText :msg="errorMessage('idprojects', 'driver.idprojects')" />
                </div>
              </FieldRow>

              <FieldRow label="Shift:" class="md:col-span-1">
                <div class="flex flex-wrap items-center gap-4">
                  <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                    <input v-model="form.driver.driver_shift" type="radio" :value="''" class="h-4 w-4" />
                    <span>No Preference</span>
                  </label>
                  <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                    <input v-model="form.driver.driver_shift" type="radio" :value="0" class="h-4 w-4" />
                    <span>Night</span>
                  </label>
                  <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                    <input v-model="form.driver.driver_shift" type="radio" :value="1" class="h-4 w-4" />
                    <span>Day</span>
                  </label>
                </div>
              </FieldRow>

              <FieldRow label="Spanish:" class="md:col-span-1">
                <div class="flex flex-wrap items-center gap-4">
                  <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                    <input
                        :checked="Number(form.driver.spanish_language) === 0"
                        type="radio"
                        class="h-4 w-4"
                        @change="form.driver.spanish_language = 0"
                    />
                    <span>No</span>
                  </label>
                  <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                    <input
                        :checked="Number(form.driver.spanish_language) === 1"
                        type="radio"
                        class="h-4 w-4"
                        @change="form.driver.spanish_language = 1"
                    />
                    <span>Yes</span>
                  </label>
                </div>
              </FieldRow>

              <FieldRow label="Truck:" class="md:col-span-1">
                <div>
                  <select
                      v-model="form.driver.id_vehicle"
                      class="w-full rounded-lg border bg-white px-3 py-2 text-sm"
                      :class="inputErr('id_vehicle')"
                  >
                    <option value=""></option>
                    <option
                        v-for="option in truckOptions"
                        :key="`truck-${option.value}`"
                        :value="option.value"
                    >
                      {{ option.label }}
                    </option>
                  </select>
                  <ErrText :msg="errorMessage('id_vehicle', 'driver.id_vehicle')" />
                </div>
              </FieldRow>

              <FieldRow label="Trailer:" class="md:col-span-1">
                <div>
                  <select
                      v-model="form.driver.id_trailer"
                      class="w-full rounded-lg border bg-white px-3 py-2 text-sm"
                      :class="inputErr('id_trailer')"
                  >
                    <option value=""></option>
                    <option
                        v-for="option in trailerOptions"
                        :key="`trailer-${option.value}`"
                        :value="option.value"
                    >
                      {{ option.label }}
                    </option>
                  </select>
                  <ErrText :msg="errorMessage('id_trailer', 'driver.id_trailer')" />
                </div>
              </FieldRow>

              <FieldRow label="GPS Device:" class="md:col-span-1">
                <div>
                  <input
                      v-model="form.driver.id_device"
                      type="text"
                      class="w-full max-w-[220px] rounded-lg border px-3 py-2 text-sm"
                      :class="inputErr('id_device')"
                  />
                  <ErrText :msg="errorMessage('id_device', 'driver.id_device')" />
                </div>
              </FieldRow>

              <FieldRow label="Mobile App PIN:" :nowrapLabel="true" class="md:col-span-1">
                <div>
                  <input
                      v-model="form.driver.mobile_app_pin"
                      type="text"
                      class="w-full max-w-[140px] rounded-lg border px-3 py-2 text-sm"
                      :class="inputErr('mobile_app_pin')"
                  />
                  <ErrText :msg="errorMessage('mobile_app_pin', 'driver.mobile_app_pin')" />
                </div>
              </FieldRow>

              <FieldRow label="Status:" class="md:col-span-1">
                <div>
                  <select
                      v-model="form.driver.status"
                      class="w-full max-w-[220px] rounded-lg border bg-white px-3 py-2 text-sm"
                      :class="inputErr('status')"
                  >
                    <option value=""></option>
                    <option
                        v-for="option in statusOptions"
                        :key="`status-${option.value}`"
                        :value="option.value"
                    >
                      {{ option.label }}
                    </option>
                  </select>
                  <ErrText :msg="errorMessage('status', 'driver.status')" />
                </div>
              </FieldRow>
            </div>

            <div class="mt-3 rounded-lg bg-gray-50 p-4">
              <div class="text-sm font-semibold text-gray-800">TCS Fuel</div>

              <div class="mt-2 grid grid-cols-1 gap-y-2 md:grid-cols-2 md:gap-x-6">
                <FieldRow label="Card #:" class="md:col-span-1">
                  <div>
                    <input
                        v-model="form.driver.tcs_fuel_card_number"
                        type="text"
                        class="w-full max-w-[220px] rounded-lg border px-3 py-2 text-sm"
                        :class="inputErr('tcs_fuel_card_number')"
                    />
                    <ErrText :msg="errorMessage('tcs_fuel_card_number', 'driver.tcs_fuel_card_number')" />
                  </div>
                </FieldRow>

                <FieldRow label="PIN:" class="md:col-span-1">
                  <div>
                    <input
                        v-model="form.driver.tcs_fuel_card_pin"
                        type="text"
                        class="w-full max-w-[140px] rounded-lg border px-3 py-2 text-sm"
                        :class="inputErr('tcs_fuel_card_pin')"
                    />
                    <ErrText :msg="errorMessage('tcs_fuel_card_pin', 'driver.tcs_fuel_card_pin')" />
                  </div>
                </FieldRow>

                <FieldRow label="Limit:" class="md:col-span-1">
                  <div>
                    <input
                        v-model="form.driver.tcs_fuel_card_limit"
                        type="text"
                        class="w-full max-w-[220px] rounded-lg border px-3 py-2 text-sm"
                        :class="inputErr('tcs_fuel_card_limit')"
                    />
                    <ErrText :msg="errorMessage('tcs_fuel_card_limit', 'driver.tcs_fuel_card_limit')" />
                  </div>
                </FieldRow>

                <FieldRow label="Last Updated:" class="md:col-span-1">
                  <div class="text-sm text-gray-800">
                    {{ form.driver.tcs_fuel_card_last_updated || '—' }}
                  </div>
                </FieldRow>
              </div>
            </div>
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
        <div></div>

        <div class="flex items-center gap-2">
          <button
              type="button"
              class="rounded-lg border border-gray-300 px-4 py-2 text-sm hover:bg-gray-50 disabled:opacity-50"
              :disabled="saving"
              @click="emit('close')"
          >
            Cancel
          </button>

          <button
              type="button"
              class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800 disabled:opacity-50"
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
import { computed, defineComponent, h, watch } from 'vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
  saving: { type: Boolean, default: false },
  form: { type: Object, required: true },
  errors: { type: Object, default: () => ({}) },
  formError: { type: String, default: '' },
  lookups: { type: Object, default: () => ({}) },
  driverToggle: { type: Boolean, default: true },
})

const emit = defineEmits(['close', 'save', 'update:driverToggle'])

function stripVehiclePrefix(value) {
  return String(value ?? '')
      .replace(/^Vehicle\s*#\s*/i, '')
      .trim()
}

function optionValue(item, ...keys) {
  for (const key of keys) {
    if (item?.[key] !== undefined && item?.[key] !== null && item?.[key] !== '') {
      return item[key]
    }
  }
  return ''
}

function optionLabel(item, ...keys) {
  for (const key of keys) {
    if (item?.[key] !== undefined && item?.[key] !== null && item?.[key] !== '') {
      return item[key]
    }
  }
  return ''
}

function asOptionList(source, valueKeys, labelKeys) {
  if (!Array.isArray(source)) {
    return []
  }

  return source
      .map((item) => {
        const value = optionValue(item, ...valueKeys)
        const label = optionLabel(item, ...labelKeys)

        return {
          value,
          label,
        }
      })
      .filter((item) => item.value !== '' || item.label !== '')
}

function errorMessage(...keys) {
  for (const key of keys) {
    if (props.errors?.[key]) {
      return props.errors[key]
    }
  }
  return ''
}

function normalizeDriverShiftValue(value) {
  if (value === null || value === undefined || value === '') {
    return ''
  }

  if (value === 0 || value === '0') {
    return 0
  }

  if (value === 1 || value === '1') {
    return 1
  }

  if (typeof value === 'string') {
    const normalized = value.trim().toLowerCase()

    if (normalized === 'night') {
      return 0
    }

    if (normalized === 'day') {
      return 1
    }
  }

  return value
}

watch(
    () => props.form?.driver?.driver_shift,
    (value) => {
      const normalized = normalizeDriverShiftValue(value)
      if (normalized !== value && props.form?.driver) {
        props.form.driver.driver_shift = normalized
      }
    },
    { immediate: true }
)

const modalTitle = computed(() => {
  return Number(props.form?.contact?.id_contact || 0) > 0 || Number(props.form?.driver?.id_driver || 0) > 0
      ? 'Edit Contact'
      : 'New Contact'
})

const stateOptions = computed(() =>
    asOptionList(
        props.lookups?.states || [],
        ['value', 'id', 'state_code', 'abbr', 'state'],
        ['label', 'name', 'state_name', 'abbr', 'state']
    )
)

const carrierOptions = computed(() =>
    asOptionList(
        props.lookups?.carriers || [],
        ['value', 'id_carrier', 'id'],
        ['label', 'name', 'carrier_name']
    )
)

const projectOptions = computed(() =>
    asOptionList(
        props.lookups?.projects || [],
        ['value', 'idprojects', 'id'],
        ['label', 'projectname', 'name']
    )
)

const truckOptions = computed(() =>
    (props.lookups?.trucks || []).map((item) => ({
      value: optionValue(item, 'value', 'id_vehicle', 'id'),
      label: stripVehiclePrefix(optionLabel(item, 'label', 'vehicle_name', 'vehicle_number', 'name')),
    }))
)

const trailerOptions = computed(() =>
    (props.lookups?.trailers || []).map((item) => ({
      value: optionValue(item, 'value', 'id_vehicle', 'id'),
      label: stripVehiclePrefix(optionLabel(item, 'label', 'vehicle_name', 'vehicle_number', 'name')),
    }))
)

const statusOptions = computed(() => {
  const fromLookups = props.lookups?.statuses || []

  if (fromLookups.length) {
    return asOptionList(fromLookups, ['value', 'id', 'status'], ['label', 'name', 'status'])
  }

  return [
    { value: 'Active', label: 'Active' },
    { value: 'Inactive', label: 'Inactive' },
    { value: 'Pending', label: 'Pending' },
  ]
})

function inputErr(key) {
  return props.errors?.[key] || props.errors?.[`contact.${key}`] || props.errors?.[`driver.${key}`]
      ? 'border-red-400 ring-2 ring-red-100'
      : 'border-gray-300'
}

const FieldRow = defineComponent({
  name: 'FieldRow',
  props: {
    label: { type: String, required: true },
    nowrapLabel: { type: Boolean, default: false },
  },
  setup(fieldProps, { slots, attrs }) {
    return () =>
        h(
            'div',
            { class: attrs.class || '' },
            h('div', { class: 'grid grid-cols-[160px_minmax(0,1fr)] items-center gap-2' }, [
              h(
                  'div',
                  {
                    class:
                        'text-sm text-gray-800 font-semibold text-right pr-1' +
                        (fieldProps.nowrapLabel ? ' whitespace-nowrap' : ''),
                  },
                  fieldProps.label
              ),
              h('div', { class: 'min-w-0' }, slots.default ? slots.default() : null),
            ])
        )
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