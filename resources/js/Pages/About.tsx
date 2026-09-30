import Layout from '../Components/layout/Layout'

export default function About() {
    return (
        <Layout>
            <div className="mx-auto max-w-3xl px-4 py-12">
                <h1 className="mb-2 text-2xl font-bold text-white">About The Cosmic</h1>
                <div className="mb-8 h-px bg-[#2A2A2A]" />

                <div className="space-y-5 text-sm leading-relaxed text-[#777]">
                    <p>
                        The Cosmic is a modern, fast, and distraction-free manga, manhwa, and manhua reader.
                        Built with a reader-first philosophy — no clutter, no ads, just the content you love.
                    </p>

                    <p>
                        All content is sourced directly from the komikcast API, providing access to
                        thousands of series across every genre. From action-packed shonen to slice-of-life
                        romance, The Cosmic brings you the latest chapters as they drop.
                    </p>

                    <h2 className="pt-4 text-base font-bold text-white">Features</h2>

                    <ul className="list-inside list-disc space-y-1.5 text-sm text-[#777]">
                        <li>Minimalist monochrome interface — easy on the eyes</li>
                        <li>Flawless chapter reader with image preloading</li>
                        <li>Trending, genre, and format filters for quick discovery</li>
                        <li>Fast search by title</li>
                        <li>No account required to read</li>
                        <li>Responsive design — works on mobile, tablet, and desktop</li>
                    </ul>

                    <h2 className="pt-4 text-base font-bold text-white">Disclaimer</h2>

                    <p>
                        The Cosmic is an independent fan project and is not affiliated with any publisher
                        or official scanlation group. All series data and images are fetched from publicly
                        available sources. If you enjoy a series, please support the official release.
                    </p>
                </div>
            </div>
        </Layout>
    )
}
