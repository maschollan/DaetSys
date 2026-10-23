import http from '@/services/http'

/**
 * Wrapper endpoint domain backend. Setiap helper mengembalikan Promise axios;
 * konvensi respons backend:
 * - List: format paginator Laravel { data, links, meta, message }.
 * - Detail/mutasi: { data?, message }.
 * - 422 validasi: { message, errors: { field: [..] } }.
 */

const buildQuery = (params = {}) => {
  const query = {}

  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return
    query[key] = value
  })

  return query
}

export const assets = {
  list: (params = {}) => http.get('/assets', { params: buildQuery(params) }),
  get: (id) => http.get(`/assets/${id}`),
  create: (payload, idempotencyKey = null) =>
    http.post('/assets', payload, {
      headers: idempotencyKey ? { 'Idempotency-Key': idempotencyKey } : {},
    }),
  update: (id, payload) => http.put(`/assets/${id}`, payload),
  remove: (id) => http.delete(`/assets/${id}`),
  history: (id, params = {}) => http.get(`/assets/${id}/history`, { params: buildQuery(params) }),
  byQrToken: (qrToken) => http.get(`/assets/qr/${encodeURIComponent(qrToken)}`),
}

export const locations = {
  list: (params = {}) => http.get('/locations', { params: buildQuery(params) }),
  all: (params = { per_page: 100, sort_by: 'name', sort_direction: 'asc' }) =>
    http.get('/locations', { params: buildQuery(params) }),
  get: (id) => http.get(`/locations/${id}`),
  create: (payload) => http.post('/locations', payload),
  update: (id, payload) => http.put(`/locations/${id}`, payload),
  remove: (id) => http.delete(`/locations/${id}`),
}

export const categories = {
  list: (params = {}) => http.get('/asset-categories', { params: buildQuery(params) }),
  all: (params = { per_page: 100, sort_by: 'name', sort_direction: 'asc' }) =>
    http.get('/asset-categories', { params: buildQuery(params) }),
  create: (payload) => http.post('/asset-categories', payload),
  update: (id, payload) => http.put(`/asset-categories/${id}`, payload),
  remove: (id) => http.delete(`/asset-categories/${id}`),
}

export const transfers = {
  list: (params = {}) => http.get('/transfers', { params: buildQuery(params) }),
  get: (id) => http.get(`/transfers/${id}`),
  create: (payload, idempotencyKey = null) =>
    http.post('/transfers', payload, {
      headers: idempotencyKey ? { 'Idempotency-Key': idempotencyKey } : {},
    }),
  approve: (id, notes = null) => http.post(`/transfers/${id}/approve`, { notes }),
  reject: (id, notes) => http.post(`/transfers/${id}/reject`, { notes }),
  handover: (id, notes = null) => http.post(`/transfers/${id}/handover`, { notes }),
  receive: (id, notes = null) => http.post(`/transfers/${id}/receive`, { notes }),
  cancel: (id, reason = null) => http.post(`/transfers/${id}/cancel`, { reason }),
}

export const dashboard = {
  summary: () => http.get('/dashboard/summary'),
  assetsByLocation: () => http.get('/dashboard/assets-by-location'),
  assetsByStatus: () => http.get('/dashboard/assets-by-status'),
  recentActivities: () => http.get('/dashboard/recent-activities'),
  pendingTransfers: () => http.get('/dashboard/pending-transfers'),
}

export const notifications = {
  list: (params = {}) => http.get('/notifications', { params: buildQuery(params) }),
  markAsRead: (id) => http.post(`/notifications/${id}/read`),
  markAllAsRead: () => http.post('/notifications/read-all'),
}

export const activityLogs = {
  list: (params = {}) => http.get('/activity-logs', { params: buildQuery(params) }),
  get: (id) => http.get(`/activity-logs/${id}`),
}

export const users = {
  list: (params = {}) => http.get('/users', { params: buildQuery(params) }),
  get: (id) => http.get(`/users/${id}`),
  create: (payload) => http.post('/users', payload),
  update: (id, payload) => http.put(`/users/${id}`, payload),
  remove: (id) => http.delete(`/users/${id}`),
  resetPassword: (id, payload) => http.post(`/users/${id}/reset-password`, payload),
}

/**
 * Report CSV dikembalikan sebagai blob agar dapat diunduh langsung oleh
 * komponen laporan tanpa membuka tab berautentikasi cookie.
 */
export const reports = {
  assets: (params = {}) => http.get('/reports/assets', { params: buildQuery(params) }),
  transfers: (params = {}) => http.get('/reports/transfers', { params: buildQuery(params) }),
  activityLogs: (params = {}) => http.get('/reports/activity-logs', { params: buildQuery(params) }),
  csv: (endpoint, params = {}) =>
    http.get(`/reports/${endpoint}`, {
      params: buildQuery({ ...params, format: 'csv' }),
      responseType: 'blob',
    }),
}

export default {
  assets,
  locations,
  categories,
  transfers,
  dashboard,
  notifications,
  activityLogs,
  users,
  reports,
}
