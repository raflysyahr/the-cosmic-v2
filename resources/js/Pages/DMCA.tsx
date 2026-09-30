import Layout from '../Components/layout/Layout'

export default function DMCA() {
    return (
        <Layout>
            <div className="mx-auto max-w-3xl px-4 py-12">
                <h1 className="mb-2 text-2xl font-bold text-white">DMCA Notice</h1>
                <div className="mb-8 h-px bg-[#2A2A2A]" />

                <div className="space-y-5 text-sm leading-relaxed text-[#777]">
                    <p>
                        The Cosmic respects the intellectual property rights of others and expects its users
                        to do the same. In accordance with the Digital Millennium Copyright Act (DMCA),
                        we will respond expeditiously to notices of alleged infringement.
                    </p>

                    <h2 className="pt-4 text-base font-bold text-white">Copyright Infringement Notification</h2>

                    <p>
                        If you believe that material available on The Cosmic infringes a copyright you own,
                        please submit a written notification containing the following information:
                    </p>

                    <ul className="list-inside list-disc space-y-1.5 text-sm text-[#777]">
                        <li>A physical or electronic signature of the copyright owner or authorized agent</li>
                        <li>Identification of the copyrighted work claimed to have been infringed</li>
                        <li>Identification of the material that is claimed to be infringing, with enough detail to locate it</li>
                        <li>Your contact information — address, telephone number, and email address</li>
                        <li>A statement that you have a good faith belief that the use is not authorized by the copyright owner</li>
                        <li>A statement that the information in the notification is accurate, under penalty of perjury</li>
                    </ul>

                    <h2 className="pt-4 text-base font-bold text-white">Takedown Procedure</h2>

                    <p>
                        Upon receiving a valid DMCA notice, we will promptly remove or disable access to
                        the allegedly infringing material and notify the affected party.
                    </p>

                    <h2 className="pt-4 text-base font-bold text-white">Counter-Notification</h2>

                    <p>
                        If you believe that material was removed or disabled as a result of mistake or
                        misidentification, you may submit a counter-notification with the same contact
                        information and a statement under penalty of perjury.
                    </p>

                    <h2 className="pt-4 text-base font-bold text-white">Contact</h2>

                    <p>
                        DMCA takedown notices and counter-notifications can be submitted through the
                        repository issues or project contact channels.
                    </p>

                    <p className="pt-4 text-[10px] text-[#555]">
                        The Cosmic is an independent fan project. Content belongs to its respective creators
                        and publishers.
                    </p>
                </div>
            </div>
        </Layout>
    )
}
