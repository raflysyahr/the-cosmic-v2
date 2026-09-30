import Layout from '../Components/layout/Layout'

export default function Privacy() {
    return (
        <Layout>
            <div className="mx-auto max-w-3xl px-4 py-12">
                <h1 className="mb-2 text-2xl font-bold text-white">Privacy Policy</h1>
                <div className="mb-8 h-px bg-[#2A2A2A]" />

                <div className="space-y-5 text-sm leading-relaxed text-[#777]">
                    <p>
                        The Cosmic respects your privacy. This policy outlines what information we collect and how it is used.
                    </p>

                    <h2 className="pt-4 text-base font-bold text-white">Information We Collect</h2>

                    <p>
                        The Cosmic does not collect or store any personal data. We do not require an account
                        to browse or read content. No cookies, trackers, or analytics scripts are used on this site.
                    </p>

                    <h2 className="pt-4 text-base font-bold text-white">Third-Party Services</h2>

                    <p>
                        All content (series data, cover images, chapter pages) is loaded from the
                        publicly available komikcast API at <span className="text-[#999]">be.komikcast.cc</span>.
                        Image assets are served from <span className="text-[#999]">sv1.imgkc1.my.id</span>.
                        These services may have their own privacy policies.
                    </p>

                    <h2 className="pt-4 text-base font-bold text-white">Local Storage</h2>

                    <p>
                        The Cosmic uses local storage solely for user preferences such as bookmarks and
                        reading history. No personal data is transmitted externally.
                    </p>

                    <h2 className="pt-4 text-base font-bold text-white">Contact</h2>

                    <p>
                        If you have questions about this privacy policy, please reach out via the
                        repository or contact channels associated with this project.
                    </p>

                    <p className="pt-4 text-[10px] text-[#555]">
                        Last updated: June 2026
                    </p>
                </div>
            </div>
        </Layout>
    )
}
