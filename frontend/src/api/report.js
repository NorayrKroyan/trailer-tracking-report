const OWNERS_URL = '/api/reports/trailer-tracking/owners'
const REPORT_URL = '/api/reports/trailer-tracking'

async function requestJson(url, options = {}) {
    const response = await fetch(url, {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(options.body ? { 'Content-Type': 'application/json' } : {}),
            ...(options.headers || {}),
        },
        ...options,
    })

    const text = await response.text()
    const data = text ? JSON.parse(text) : {}

    if (!response.ok) {
        throw new Error(data?.message || `Request failed with status ${response.status}`)
    }

    return data
}

export async function fetchOwners() {
    return requestJson(OWNERS_URL)
}

export async function fetchTrailerTracking(params = {}) {
    const qs = new URLSearchParams()

    Object.entries(params).forEach(([key, value]) => {
        if (value === null || value === undefined || value === '') {
            return
        }

        qs.append(key, value)
    })

    const url = qs.toString() ? `${REPORT_URL}?${qs.toString()}` : REPORT_URL

    return requestJson(url)
}

export async function fetchVehicleEditor(vehicleId) {
    return requestJson(`${REPORT_URL}/vehicle/${vehicleId}`)
}

export async function saveVehicleEditor(vehicleId, payload) {
    return requestJson(`${REPORT_URL}/vehicle/${vehicleId}`, {
        method: 'PUT',
        body: JSON.stringify(payload),
    })
}

export async function fetchDriverEditor(contactId) {
    return requestJson(`${REPORT_URL}/driver/${contactId}`)
}

export async function saveDriverEditor(contactId, payload) {
    return requestJson(`${REPORT_URL}/driver/${contactId}`, {
        method: 'PUT',
        body: JSON.stringify(payload),
    })
}