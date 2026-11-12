import { onMounted, onUnmounted, ref } from 'vue'
import { kioskApi } from '@/api'
import { useKioskStore } from '@/stores/kiosk'
import { useRouter } from 'vue-router'

/**
 * Auto-reset kiosk (FR-K-10).
 * Setelah `kiosk_idle_detik` tanpa interaksi, tampilkan hitung mundur overlay;
 * bila tidak ada interaksi sampai hitung mundur habis, bersihkan seluruh sesi
 * dan kembali ke beranda. Setiap interaksi membatalkan hitung mundur dan
 * memulai ulang timer idle.
 */
export function useIdleKiosk({ detikMundur = 10 } = {}) {
  const router = useRouter()
  const kioskStore = useKioskStore()

  const idleDetik = ref(120)
  const hitungMundur = ref(null) // null = overlay tertutup, angka = sisa detik overlay
  const aktif = ref(false)

  let timerIdle = null
  let timerMundur = null

  const ACARA = ['pointermove', 'pointerdown', 'keydown', 'touchstart', 'scroll']

  function mulaiIdle() {
    bersihkanTimer()
    if (!aktif.value) return
    timerIdle = setTimeout(terpicuIdle, idleDetik.value * 1000)
  }

  function terpicuIdle() {
    hitungMundur.value = detikMundur
    timerMundur = setInterval(() => {
      hitungMundur.value -= 1
      if (hitungMundur.value <= 0) {
        aturUlang()
      }
    }, 1000)
  }

  function batalkanMundur() {
    clearInterval(timerMundur)
    timerMundur = null
    hitungMundur.value = null
  }

  /** Bersihkan sesi, kembali ke beranda, lalu mulai idle dari awal. */
  function aturUlang() {
    batalkanMundur()
    kioskStore.resetSesi()
    router.push('/')
    mulaiIdle()
  }

  /** Interaksi pengguna saat overlay terbuka membatalkan dan mulai idle ulang. */
  function onInteraksi() {
    if (hitungMundur.value !== null) {
      batalkanMundur()
      mulaiIdle()
      return
    }
    // Reset timer idle biasa
    clearTimeout(timerIdle)
    mulaiIdle()
  }

  function bersihkanTimer() {
    clearTimeout(timerIdle)
    clearInterval(timerMundur)
    timerIdle = null
    timerMundur = null
  }

  onMounted(async () => {
    aktif.value = true

    try {
      const res = await kioskApi.getConfig()
      const detik = Number(res.data?.data?.kiosk_idle_detik) || 0
      if (detik > 0) {
        idleDetik.value = detik
      }
    } catch {
      // Gagal ambil config: pakai bawaan 120 detik (ponytail: nilai aman)
    }

    mulaiIdle()
    for (const acara of ACARA) {
      window.addEventListener(acara, onInteraksi, { passive: true })
    }
  })

  onUnmounted(() => {
    aktif.value = false
    bersihkanTimer()
    for (const acara of ACARA) {
      window.removeEventListener(acara, onInteraksi)
    }
  })

  return {
    hitungMundur,
    aturUlang,
    batalkanMundur,
  }
}
