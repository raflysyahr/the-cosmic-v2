import Layout from "../Components/layout/Layout"

export default function Contact() {
    return (
        <Layout>
            <div className="mx-auto max-w-3xl px-4 py-12">
                <h1 className="mb-2 text-2xl font-bold text-white">Contact & Support</h1>
                <div className="mb-8 h-px bg-[#2A2A2A]" />

                <div className="space-y-5 text-sm leading-relaxed text-[#777]">
                    <p>
                        Have a question, found a bug, or want to request a feature? Here is how to reach us.
                    </p>

                    <h2 className="pt-4 text-base font-bold text-white">Bug Reports & Feature Requests</h2>

                    <p>
                        The best way to report issues or suggest improvements is through the project's
                        GitHub Issues page. Include as much detail as possible — browser, page URL,
                        and steps to reproduce the issue.
                    </p>

                    <ul className="list-inside list-disc space-y-1.5 text-sm text-[#777]">
                        <li>Broken chapter images or missing pages</li>
                        <li>Search not returning expected results</li>
                        <li>Layout or display issues on your device</li>
                        <li>Feature suggestions — reader improvements, filters, bookmarks</li>
                    </ul>

                    <h2 className="pt-4 text-base font-bold text-white">Common Issues</h2>

                    <p>
                        <span className="font-semibold text-[#999]">Images not loading?</span> Try refreshing
                        the page. Some CDN servers may take a moment to respond. If the issue persists,
                        the source API may be temporarily unavailable.
                    </p>

                    <p>
                        <span className="font-semibold text-[#999]">Broken chapter?</span> Check if other
                        chapters of the same series load correctly. If only one chapter is broken,
                        report it via GitHub Issues with the series name and chapter number.
                    </p>

                    <h2 className="pt-4 text-base font-bold text-white">Disclaimer</h2>

                    <p>
                        The Cosmic is a fan project maintained in spare time. Response times may vary.
                        For urgent copyright matters, please refer to the DMCA page instead.
                    </p>

                    <p className="pt-4 text-[10px] text-[#555]">
                        GitHub:{' '}
                        <a
                            href="https://github.com/anomalyco/opencode"
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-[#999] underline transition-colors hover:text-white"
                        >
                            github.com/anomalyco/opencode
                        </a>
                    </p>
                </div>
            </div>
        </Layout>
    )
}
