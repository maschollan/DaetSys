/**
 * Versi Options API dari useModalEscape: penutupan tambahan untuk modal
 * manual (`display:block`) via tombol Esc.
 *
 * Kontrak: komponen pemakai memiliki prop `show` dan method `close()` —
 * klik latar ditangani langsung di template lewat `@click.self="close"`.
 */
export default {
  created() {
    this.dsEscapeHandler = (event) => {
      if (event.key === 'Escape' && this.show) {
        this.close()
      }
    }
  },

  watch: {
    show: {
      handler(open) {
        if (open) window.addEventListener('keydown', this.dsEscapeHandler)
        else window.removeEventListener('keydown', this.dsEscapeHandler)
      },
      immediate: true,
    },
  },

  beforeUnmount() {
    window.removeEventListener('keydown', this.dsEscapeHandler)
  },
}
