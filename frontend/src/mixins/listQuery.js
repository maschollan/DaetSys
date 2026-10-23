/**
 * Versi Options API dari useListQuery: state daftar generik untuk endpoint
 * paginated backend (format paginator Laravel).
 *
 * Konfigurasi per komponen lewat opsi kustom `listConfig`:
 *   listConfig: {
 *     fetch: api.assets.list,           // wajib: fungsi klien API
 *     filters: { status: '' },          // opsional: nilai awal filter
 *     sort: { key: 'name', dir: 'asc' } // opsional: sort default
 *   }
 *
 * State data: rows, meta, links, loading, error, searchQuery, sortKey,
 * sortDir, filters.
 * Method: refresh, goToPage, setPerPage, applyFilters, applySearch,
 * toggleSort.
 *
 * - sortKey/sortDir diubah lewat toggleSort: perubahan memicu fetch ulang.
 * - filter tambahan dikelola via `filters` (objek data): cukup ubah nilainya
 *   lalu panggil applyFilters().
 * - `error` berisi pesan ramah untuk ditampilkan (422/403/409/500).
 * - State (search, filter, sort, halaman, per_page) disinkronkan dengan Vue
 *   Router query: deep-link seperti /assets?status=missing memuat filter
 *   secara otomatis, dan perubahan filter diperbarui pada URL.
 */
const PER_PAGE_OPTIONS = [10, 15, 25, 50, 100]
const PER_PAGE_DEFAULT = 15

export default {
  data() {
    const config = this.$options.listConfig ?? {}

    return {
      rows: [],
      meta: { current_page: 1, last_page: 1, total: 0, per_page: 15 },
      links: { next: null, prev: null },
      loading: false,
      error: '',
      searchQuery: '',
      sortKey: config.sort?.key ?? null,
      sortDir: config.sort?.dir ?? 'desc',
      filters: { ...(config.filters ?? {}) },
    }
  },

  created() {
    this.dsListToken = 0
    this.dsListLastSignature = ''
    this.dsListFilterKeys = Object.keys((this.$options.listConfig ?? {}).filters ?? {})
  },

  methods: {
    /** Muat state awal dari $route.query (deep-link dari halaman lain). */
    hydrateListFromQuery() {
      const query = this.$route.query

      const search = this.listQueryParam(query.search)
      if (search) this.searchQuery = search

      const sortBy = this.listQueryParam(query.sort_by)
      if (sortBy) {
        this.sortKey = sortBy
        const direction = this.listQueryParam(query.sort_direction)
        if (['asc', 'desc'].includes(direction)) this.sortDir = direction
      }

      const page = Number(this.listQueryParam(query.page))
      if (Number.isInteger(page) && page > 1) this.meta.current_page = page

      const perPage = Number(this.listQueryParam(query.per_page))
      if (PER_PAGE_OPTIONS.includes(perPage)) this.meta.per_page = perPage

      this.dsListFilterKeys.forEach((key) => {
        const raw = this.listQueryParam(query[key])
        if (raw) this.filters[key] = raw
      })
    },

    listQueryParam(value) {
      return typeof value === 'string' ? value : ''
    },

    /** Susun query URL dari state saat ini (param kosong dilewati). */
    buildListQueryPayload() {
      const query = {}

      if (this.searchQuery.trim()) query.search = this.searchQuery.trim()
      this.dsListFilterKeys.forEach((key) => {
        if (String(this.filters[key] ?? '').trim()) query[key] = String(this.filters[key])
      })
      if (this.sortKey) {
        query.sort_by = this.sortKey
        query.sort_direction = this.sortDir
      }
      if (this.meta.current_page > 1) query.page = this.meta.current_page
      if (this.meta.per_page !== PER_PAGE_DEFAULT) query.per_page = this.meta.per_page

      return query
    },

    /** Perbarui URL tanpa memicu navigasi penuh. */
    syncListQuery() {
      const query = this.buildListQueryPayload()
      const signature = JSON.stringify(query)
      if (signature === this.dsListLastSignature) return

      this.dsListLastSignature = signature
      void this.$router.replace({ query }).catch(() => {})
    },

    hydrateListOnce() {
      if (this.dsListLastSignature) return
      this.hydrateListFromQuery()
      this.dsListLastSignature = JSON.stringify(this.buildListQueryPayload())
    },

    extractListError(payload) {
      return payload?.message || 'Gagal memuat data. Silakan coba lagi.'
    },

    async fetchRows() {
      const fetchFn = (this.$options.listConfig ?? {}).fetch
      const token = ++this.dsListToken
      this.loading = true
      this.error = ''
      this.syncListQuery()

      try {
        const { data } = await fetchFn({
          page: this.meta.current_page,
          per_page: this.meta.per_page,
          search: this.searchQuery || undefined,
          sort_by: this.sortKey || undefined,
          sort_direction: this.sortKey ? this.sortDir : undefined,
          ...this.filters,
        })

        // Abaikan respons lama bila parameter sudah berubah lagi.
        if (token !== this.dsListToken) return

        this.rows = data.data ?? []
        Object.assign(this.meta, data.meta ?? {})
        this.links.next = data.links?.next ?? null
        this.links.prev = data.links?.prev ?? null
      } catch (exception) {
        if (token !== this.dsListToken) return
        this.rows = []
        Object.assign(this.meta, { current_page: 1, last_page: 1, total: 0 })
        this.error = this.extractListError(exception.response?.data)
      } finally {
        if (token === this.dsListToken) {
          this.loading = false
        }
      }
    },

    async refresh() {
      this.hydrateListOnce()
      await this.fetchRows()
    },

    async goToPage(page) {
      if (page < 1 || page > this.meta.last_page || page === this.meta.current_page) return
      this.meta.current_page = page
      await this.fetchRows()
    },

    async setPerPage(perPage) {
      this.meta.per_page = perPage
      this.meta.current_page = 1
      await this.fetchRows()
    },

    async applyFilters() {
      this.meta.current_page = 1
      await this.fetchRows()
    },

    /** Klik header kolom: ganti kolom sort, atau balik arah bila kolom sama. */
    async toggleSort(key) {
      if (this.sortKey === key) {
        this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc'
      } else {
        this.sortKey = key
        this.sortDir = 'asc'
      }
      await this.fetchRows()
    },

    /* Search dirapikan oleh komponen (debounce), lalu panggil applySearch. */
    async applySearch() {
      this.meta.current_page = 1
      await this.fetchRows()
    },
  },
}
