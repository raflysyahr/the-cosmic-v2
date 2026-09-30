import { useEffect, useState } from 'react'
import Layout from '../Components/layout/Layout'
import { fetchCultivationGuide, type CultivationEraGuide } from '../api/cultivation'

function formatNumber(n: number): string {
  return n.toLocaleString('en-US')
}

export default function CultivationGuide() {
  const [eras, setEras] = useState<CultivationEraGuide[]>([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    fetchCultivationGuide()
      .then(setEras)
      .finally(() => setLoading(false))
  }, [])

  return (
    <Layout>
      <div className="mx-auto max-w-3xl px-4 py-12">
        <h1 className="mb-2 text-2xl font-bold text-white">Cultivation System Guide</h1>
        <div className="mb-8 h-px bg-[#2A2A2A]" />

        <div className="space-y-5 text-sm leading-relaxed text-[#777]">
          <p>
            Every reader on The Cosmic walks their own path of cultivation. As you read chapters,
            leave comments, and complete missions, you gather Essence and other resources tied to
            your current Era — advancing through Stages and Realms on the way toward Level 200.
          </p>

          <h2 className="pt-4 text-base font-bold text-white">How Progress Works</h2>

          <p>
            Cultivation is measured in three layers, from smallest to largest:
          </p>

          <ul className="list-inside list-disc space-y-1.5 text-sm text-[#777]">
            <li><span className="text-white">Stage</span> — the smallest unit of progress, 1 through 10 within a Realm</li>
            <li><span className="text-white">Realm</span> — a tier made up of 10 Stages; there are 20 Realms in total</li>
            <li><span className="text-white">Era</span> — a broad chapter of the journey, grouping 4 Realms under one theme and resource</li>
          </ul>

          <p>
            Completing Stage 10 of a Realm triggers a breakthrough into the next Realm at Stage 1.
            Your overall Level (1–200) is simply your position across all 20 Realms combined — Level 1
            is the very first Stage of the very first Realm, and Level 200 is the final Stage of the
            final Realm.
          </p>

          <h2 className="pt-4 text-base font-bold text-white">Earning Progress</h2>

          <p>
            Progress is earned from genuine engagement with the platform, not idle activity:
          </p>

          <ul className="list-inside list-disc space-y-1.5 text-sm text-[#777]">
            <li>Reading a chapter to completion (all pages loaded, scrolled to the end, spent enough time reading)</li>
            <li>Posting comments on series and chapters</li>
            <li>Completing missions from the Mission Board</li>
          </ul>

          <p>
            Each source only counts once per unique action — re-reading a chapter you already
            finished won't grant additional progress a second time.
          </p>

          <h2 className="pt-4 text-base font-bold text-white">The Five Eras</h2>

          <p>
            Every Era has its own signature resource, earned and spent purely within that Era's
            4 Realms. The amount of progress required to advance roughly doubles with every Realm,
            so later Eras represent a meaningfully longer journey than the first.
          </p>

          {loading && (
            <p className="text-[#555]">Loading realm data…</p>
          )}

          {!loading && eras.length === 0 && (
            <p className="text-[#555]">Realm data is currently unavailable.</p>
          )}

          {!loading && eras.map((era) => (
            <div key={era.id} className="pt-2">
              <h3 className="text-sm font-bold text-white">
                {era.name} <span className="font-normal text-[#777]">— Resource: {era.resource_name}</span>
              </h3>

              <div className="mt-3 overflow-hidden rounded-lg border border-[#2A2A2A]">
                <table className="w-full text-left text-xs">
                  <thead>
                    <tr className="bg-[#1A1A1A] text-[#999]">
                      <th className="px-3 py-2 font-medium">Realm</th>
                      <th className="px-3 py-2 font-medium">Levels</th>
                      <th className="px-3 py-2 font-medium text-right">Progress per Stage</th>
                    </tr>
                  </thead>
                  <tbody>
                    {era.realms.map((realm, i) => (
                      <tr
                        key={realm.id}
                        className={i % 2 === 0 ? 'bg-transparent' : 'bg-[#161616]'}
                      >
                        <td className="px-3 py-2 text-white">{realm.full_name}</td>
                        <td className="px-3 py-2 text-[#777]">{realm.level_start}–{realm.level_end}</td>
                        <td className="px-3 py-2 text-right text-[#777]">
                          {formatNumber(realm.stage_required)} {era.resource_name}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          ))}

          <h2 className="pt-4 text-base font-bold text-white">Reaching the End</h2>

          <p>
            The final Realm of the final Era caps out at Level 200. Once you've reached its last
            Stage, any further progress from that point on is simply a record of your continued
            dedication — there is no higher Level to climb beyond it.
          </p>
        </div>
      </div>
    </Layout>
  )
}
