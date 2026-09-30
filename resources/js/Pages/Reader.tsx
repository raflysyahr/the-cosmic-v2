import { Link } from '@inertiajs/react'
import { useState, useEffect, useRef } from 'react'
import { fetchChapters, fetchSeriesBySlug } from '../api/series'
import client from '../api/client'
import { useChapter } from '../hooks/useChapter'
import { useReadingHistory } from '../hooks/useReadingHistory'
import { ChevronLeft, ChevronRight, List, Play, Pause, Maximize, Minimize } from 'lucide-react'
import type { ChapterItem } from '../types'
import Skeleton from '../Components/ui/Skeleton'
import CultivationToastStack from '../Components/cultivation/CultivationToastStack'
import ModalHost from '../Components/ui/Modal'
import { useAuth } from '../contexts/AuthContext'
import { useCultivationToast } from '../contexts/CultivationToastContext'

export default function Reader({ slug, chapterSlug }: { slug: string; chapterSlug: string }) {
  const chapterIndex = parseInt(chapterSlug || '0')

  const [chapters, setChapters] = useState<ChapterItem[]>([])
  const [loading, setLoading] = useState(true)
  const [chapterListOpen, setChapterListOpen] = useState(false)
  const [autoScrolling, setAutoScrolling] = useState(false)
  const [scrollSpeed, setScrollSpeed] = useState<'slow' | 'medium' | 'fast'>('medium')
  const scrollTimerRef = useRef<ReturnType<typeof setInterval> | null>(null)
  const [navOpen, setNavOpen] = useState(true)
  const [navFloating, setNavFloating] = useState(false)
  const [fullscreen, setFullscreen] = useState(false)

  const { images, loading: pagesLoading, error: pagesError } = useChapter(slug || '', chapterIndex)
  const { user } = useAuth()
  const userId = user?.id
  const { markRead } = useReadingHistory(userId)
  const { showGain } = useCultivationToast()

  const currentIdx = chapters.findIndex((c) => c.index === chapterIndex)
  const current = chapters[currentIdx]
  const prevChapter = chapters[currentIdx + 1]
  const nextChapter = chapters[currentIdx - 1]

  const [seriesMeta, setSeriesMeta] = useState<{ title: string; cover: string } | null>(null)
  const seriesFetchedRef = useRef(false)

  useEffect(() => {
    if (!slug || !chapterIndex || !seriesMeta || !current) return
    markRead(slug, chapterIndex, {
      title: seriesMeta.title,
      coverImage: seriesMeta.cover,
      chapterTitle: current.title || `Chapter ${chapterIndex}`,
      chapterUrl: `/series/${slug}/chapter/${chapterIndex}`,
    })
  }, [slug, chapterIndex, markRead, seriesMeta, current])

  // --- Cultivation XP: anti-curang ------------------------------------
  // XP "chapter selesai" HANYA diberikan kalau ketiga syarat terpenuhi:
  //   1. SEMUA gambar berhasil ter-load (bukan gagal/broken karena
  //      jaringan buruk) — dilacak per-<img> lewat onLoad/onError.
  //   2. User scroll sampai benar-benar mentok bawah halaman.
  //   3. Total waktu sejak chapter dibuka > 6 detik (dicek ulang di
  //      server juga — lihat CultivationController::completeChapter).
  // startTimeRef di-reset tiap ganti chapter (dependency [slug, chapterIndex]).
  const startTimeRef = useRef<number>(Date.now())
  const [loadedImageCount, setLoadedImageCount] = useState(0)
  const [erroredImageCount, setErroredImageCount] = useState(0)
  const [scrolledToBottom, setScrolledToBottom] = useState(false)
  const xpClaimedRef = useRef(false)
  const lastImageRef = useRef<HTMLImageElement | null>(null)

  useEffect(() => {
    startTimeRef.current = Date.now()
    setLoadedImageCount(0)
    setErroredImageCount(0)
    setScrolledToBottom(false)
    xpClaimedRef.current = false
  }, [slug, chapterIndex])

  useEffect(() => {
    // Sebelumnya dicek pakai window.scrollY vs document.scrollHeight —
    // TERNYATA TIDAK AKURAT: <img loading="lazy"> tanpa width/height
    // eksplisit punya tinggi ~0px SEBELUM gambar itu mulai di-fetch,
    // jadi scrollHeight ikut berubah-ubah (jauh lebih pendek dari tinggi
    // asli halaman) selama gambar-gambar di bawah belum ter-load. User
    // yang baru scroll sedikit (chapter masih panjang di bawahnya) bisa
    // keliru terdeteksi "sudah mentok bawah" karena scrollHeight saat
    // itu kebetulan sudah tercapai secara matematis, padahal itu cuma
    // karena gambar-gambar sisanya belum "punya" tinggi.
    //
    // IntersectionObserver pada elemen GAMBAR TERAKHIR jauh lebih akurat
    // — ini murni deteksi "apakah elemen ini terlihat di viewport",
    // tidak terpengaruh perubahan tinggi elemen lain sama sekali.
    const target = lastImageRef.current
    if (!target) return

    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) setScrolledToBottom(true)
      },
      { threshold: 0.5 }, // separuh gambar terakhir harus terlihat
    )
    observer.observe(target)
    return () => observer.disconnect()
  }, [slug, chapterIndex, images.length])

  const allImagesLoaded = images.length > 0 && (loadedImageCount + erroredImageCount) >= images.length
  const allImagesOk = allImagesLoaded && erroredImageCount === 0

  useEffect(() => {
    if (xpClaimedRef.current) return
    if (!userId || !slug || !current) return
    if (!allImagesOk || !scrolledToBottom) return

    const elapsedMs = Date.now() - startTimeRef.current
    const remainingMs = 6000 - elapsedMs

    const claim = () => {
      if (xpClaimedRef.current) return
      xpClaimedRef.current = true

      client.post('/cultivation/chapter-complete', {
        slug,
        chapter_index: chapterIndex,
        started_at: new Date(startTimeRef.current).toISOString(),
      }).then((res) => {
        const gain = (res.data as { cultivation_gain?: { amount: number; resource_name: string } | null })?.cultivation_gain
        if (gain) showGain(gain.amount, gain.resource_name)
      }).catch(() => {
        // Gagal (mis. server juga menolak krn elapsed < 6s akibat clock
        // skew) — diam-diam, tidak perlu ganggu pengalaman baca.
        xpClaimedRef.current = false
      })
    }

    // Syarat gambar+scroll biasanya terpenuhi SEBELUM 6 detik berlalu
    // (user scroll cepat) — kalau begitu, tunggu sisa waktunya dulu
    // lewat setTimeout, baru klaim. Kalau sudah lewat 6 detik saat efek
    // ini jalan, klaim langsung.
    if (remainingMs <= 0) {
      claim()
      return
    }

    const t = setTimeout(claim, remainingMs)
    return () => clearTimeout(t)
  }, [userId, slug, chapterIndex, current, allImagesOk, scrolledToBottom, showGain])

  useEffect(() => {
    if (!slug || seriesFetchedRef.current) return
    seriesFetchedRef.current = true
    fetchSeriesBySlug(slug)
      .then((s) => {
        setSeriesMeta({
          title: s.title,
          cover: s.cover || '',
        })
      })
      .catch(() => {})
  }, [slug])

  useEffect(() => {
    if (!slug) return
    setLoading(true)
    fetchChapters(slug)
      .then((items) => {
        const sorted = (items || [])
          .sort((a, b) => (b.index || 0) - (a.index || 0))
        setChapters(sorted)
      })
      .catch(() => {})
      .finally(() => setLoading(false))
  }, [slug])

  // Auto-scroll effect
  useEffect(() => {
    if (!autoScrolling) {
      if (scrollTimerRef.current) {
        clearInterval(scrollTimerRef.current)
        scrollTimerRef.current = null
      }
      return
    }

    const speedMap = { slow: 1, medium: 2, fast: 4 }
    const px = speedMap[scrollSpeed]

    scrollTimerRef.current = setInterval(() => {
      const scrollH = document.documentElement.scrollHeight
      const viewH = window.innerHeight
      if (window.scrollY + viewH >= scrollH - 10) {
        setAutoScrolling(false)
        return
      }
      window.scrollBy(0, px)
    }, 30)

    return () => {
      if (scrollTimerRef.current) {
        clearInterval(scrollTimerRef.current)
        scrollTimerRef.current = null
      }
    }
  }, [autoScrolling, scrollSpeed])

  // Stop auto-scroll on chapter change
  useEffect(() => {
    setAutoScrolling(false)
    setNavOpen(true)
  }, [chapterSlug])

  // Detect whether bottom nav should float or sit at bottom
  useEffect(() => {
    const check = () => {
      setNavFloating(
        window.scrollY + window.innerHeight < document.documentElement.scrollHeight - 50
      )
    }
    check()
    window.addEventListener('scroll', check, { passive: true })
    return () => window.removeEventListener('scroll', check)
  }, [])

  // Track fullscreen state
  useEffect(() => {
    const update = () => setFullscreen(!!document.fullscreenElement)
    document.addEventListener('fullscreenchange', update)
    return () => document.removeEventListener('fullscreenchange', update)
  }, [])

  const toggleFullscreen = () => {
    if (document.fullscreenElement) {
      document.exitFullscreen()
    } else {
      document.documentElement.requestFullscreen()
    }
  }

  const cycleSpeed = () => {
    setScrollSpeed((prev) => {
      if (prev === 'slow') return 'medium'
      if (prev === 'medium') return 'fast'
      return 'slow'
    })
  }

  const speedLabel = scrollSpeed === 'slow' ? '1x' : scrollSpeed === 'medium' ? '2x' : '3x'

  if (loading || pagesLoading) {
    return (
      <div className="mx-auto max-w-4xl px-4 py-20">
        <Skeleton className="mb-4 h-6 w-40" />
        <Skeleton className="h-[80vh] w-full" />
      </div>
    )
  }

  if (pagesError || (!current && !loading)) {
    return (
      <div className="mx-auto max-w-4xl px-4 py-20 text-center">
        <p className="text-sm text-[#555]">{pagesError || 'Chapter not found'}</p>
        <Link href={`/series/${slug}`} className="mt-4 inline-flex items-center gap-1.5 text-xs text-[#777] hover:text-white transition-colors">
          <ChevronLeft className="h-3 w-3" /> Back to series
        </Link>
      </div>
    )
  }

  return (
    <div className="min-h-screen bg-black">
      {/* Reader Top Bar */}
      <div className={`sticky top-0 z-40 flex h-10 items-center justify-between border-b border-[#2A2A2A] bg-black/95 backdrop-blur px-3 ${navOpen ? '' : 'hidden'}`}>
        <div className="flex items-center gap-2">
          <Link
            href={`/series/${slug}`}
            className="flex items-center gap-1 text-xs text-[#555] transition-colors hover:text-white"
          >
            <ChevronLeft className="h-3 w-3" />
            <span className="hidden sm:inline max-w-[200px] truncate">{slug?.replace(/-/g, ' ')}</span>
          </Link>
        </div>

        <div className="flex items-center gap-2">
          <button
            onClick={() => setChapterListOpen(!chapterListOpen)}
            className="p-1.5 text-[#555] transition-colors hover:text-white"
            title="Chapter list"
          >
            <List className="h-3.5 w-3.5" />
          </button>

          <span className="text-xs font-medium text-[#999]">
            {current?.title || `Chapter ${chapterIndex}`}
          </span>

          <span className="text-[10px] text-[#555]">
            {currentIdx + 1}/{chapters.length}
          </span>
        </div>

        <div className="flex items-center gap-1">
          {prevChapter ? (
            <Link
              href={`/series/${slug}/chapter/${prevChapter.index}`}
              className="flex items-center gap-1 border border-[#2A2A2A] px-2 py-1 text-[10px] font-medium text-[#777] transition-colors hover:border-white/30 hover:text-white"
            >
              <ChevronLeft className="h-3 w-3" /> Prev
            </Link>
          ) : (
            <span className="flex items-center gap-1 border border-[#2A2A2A] px-2 py-1 text-[10px] font-medium text-[#333]">
              <ChevronLeft className="h-3 w-3" /> Prev
            </span>
          )}

          {nextChapter ? (
            <Link
              href={`/series/${slug}/chapter/${nextChapter.index}`}
              className="flex items-center gap-1 bg-white px-2 py-1 text-[10px] font-bold text-black transition-colors hover:bg-[#ccc]"
            >
              Next <ChevronRight className="h-3 w-3" />
            </Link>
          ) : (
            <span className="flex items-center gap-1 border border-[#2A2A2A] px-2 py-1 text-[10px] font-medium text-[#333]">
              Next <ChevronRight className="h-3 w-3" />
            </span>
          )}
        </div>
      </div>

      {/* Chapter List Dropdown */}
      {chapterListOpen && (
        <div
          className={`fixed left-0 right-0 z-50 max-h-80 overflow-y-auto border-b border-[#2A2A2A] bg-black ${
            navOpen ? 'top-10' : 'top-0'
          }`}
        >
          <div className="mx-auto max-w-4xl px-3 py-2">
            <div className="grid gap-px sm:grid-cols-2 lg:grid-cols-3 bg-[#2A2A2A]">
              {chapters.map((ch, i) => (
                <Link
                  key={ch.index}
                  href={`/series/${slug}/chapter/${ch.index}`}
                  onClick={() => setChapterListOpen(false)}
                  className={`px-3 py-2 text-xs transition-colors ${
                    i === currentIdx
                      ? 'bg-white text-black font-bold'
                      : 'bg-black text-[#777] hover:bg-[#1A1A1A] hover:text-white'
                  }`}
                >
                  {ch.title || `Chapter ${ch.index}`}
                </Link>
              ))}
            </div>
          </div>
        </div>
      )}

      {/* Chapter Pages — flush seamless reading */}
      <div
        className="mx-auto max-w-4xl pb-14"
        onClick={() => setNavOpen(!navOpen)}
      >
        {images.map((url, i) => (
          <img
            key={i}
            ref={i === images.length - 1 ? lastImageRef : undefined}
            src={url}
            alt={`Page ${i + 1}`}
            loading="lazy"
            className="w-full"
            onLoad={() => setLoadedImageCount((c) => c + 1)}
            onError={() => setErroredImageCount((c) => c + 1)}
          />
        ))}
      </div>

      {/* Bottom Navigation — floating when content overflows */}
      <div
        onClick={() => { if (navFloating) setNavOpen(!navOpen) }}
        className={
          navFloating
            ? 'fixed bottom-0 left-0 right-0 z-30 border-t border-[#2A2A2A] bg-black/95 backdrop-blur'
            : 'border-t border-[#2A2A2A]'
        }
      >
        {navFloating && !navOpen ? (
          /* Collapsed: thin tap handle */
          <div className="flex h-8 cursor-pointer items-center justify-center">
            <div className="h-1 w-8 rounded-full bg-[#333] transition-colors hover:bg-[#555]" />
          </div>
        ) : (
          /* Expanded: Prev / Next + secondary controls */
          <div onClick={(e) => e.stopPropagation()}>
            <div className="flex items-center justify-center gap-3 px-4 py-5">
              {prevChapter ? (
                <Link
                  href={`/series/${slug}/chapter/${prevChapter.index}`}
                  className="flex items-center gap-2 border border-[#2A2A2A] bg-[#111] px-5 py-2 text-xs font-medium text-[#777] transition-colors hover:border-white/30 hover:text-white"
                >
                  <ChevronLeft className="h-3.5 w-3.5" /> Prev
                </Link>
              ) : (
                <span className="flex items-center gap-2 border border-[#2A2A2A] px-5 py-2 text-xs font-medium text-[#333]">
                  <ChevronLeft className="h-3.5 w-3.5" /> Prev
                </span>
              )}

              {nextChapter ? (
                <Link
                  href={`/series/${slug}/chapter/${nextChapter.index}`}
                  className="flex items-center gap-2 bg-white px-5 py-2 text-xs font-bold text-black transition-colors hover:bg-[#ccc]"
                >
                  Next <ChevronRight className="h-3.5 w-3.5" />
                </Link>
              ) : (
                <span className="flex items-center gap-2 border border-[#2A2A2A] px-5 py-2 text-xs font-medium text-[#333]">
                  Next <ChevronRight className="h-3.5 w-3.5" />
                </span>
              )}
            </div>

            {/* Secondary controls — auto-scroll, chapter list */}
            <div className="flex items-center justify-center gap-4 border-t border-[#2A2A2A]/50 px-4 py-2">
              <button
                onClick={() => setAutoScrolling(!autoScrolling)}
                className={`flex items-center gap-1 text-[10px] transition-colors ${
                  autoScrolling ? 'text-white' : 'text-[#555] hover:text-white'
                }`}
              >
                {autoScrolling ? <Pause className="h-3 w-3" /> : <Play className="h-3 w-3" />}
                <span>Auto</span>
              </button>

              {autoScrolling && (
                <button
                  onClick={cycleSpeed}
                  className="text-[10px] font-bold text-[#999] transition-colors hover:text-white"
                >
                  {speedLabel}
                </button>
              )}

              <span className="text-[#2A2A2A]">|</span>

              <button
                onClick={() => setChapterListOpen(!chapterListOpen)}
                className="flex items-center gap-1 text-[10px] text-[#555] transition-colors hover:text-white"
              >
                <List className="h-3 w-3" /> Chapters
              </button>

              <span className="text-[10px] text-[#555]">
                {currentIdx + 1}/{chapters.length}
              </span>

              <span className="text-[#2A2A2A]">|</span>

              <button
                onClick={toggleFullscreen}
                className="flex items-center gap-1 text-[10px] text-[#555] transition-colors hover:text-white"
                title={fullscreen ? 'Exit fullscreen' : 'Enter fullscreen'}
              >
                {fullscreen ? <Minimize className="h-3 w-3" /> : <Maximize className="h-3 w-3" />}
              </button>
            </div>
          </div>
        )}
      </div>
      <CultivationToastStack />
      <ModalHost />
    </div>
  )
}
