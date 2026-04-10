const OWNERS_URL = '/api/reports/trailer-tracking/owners'
const REPORT_URL = '/api/reports/trailer-tracking'

async function getJson(url) {
    const response = await fetch(url, {
        method: 'GET',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    })

    const data = await response.json()

    if (!response.ok) {
        throw new Error(data?.message || `Request failed with status ${response.status}`)
    }

    return data
}

export async function fetchOwners() {
    return getJson(OWNERS_URL)
}

export async function fetchTrailerTracking(params = {}) {
    const qs = new URLSearchParams()

    Object.entries(params).forEach(([key, value]) => {
        if (value === null || value === undefined || value === '') return
        qs.append(key, value)
    })

    const url = qs.toString() ? `${REPORT_URL}?${qs.toString()}` : REPORT_URL
    return getJson(url)
}