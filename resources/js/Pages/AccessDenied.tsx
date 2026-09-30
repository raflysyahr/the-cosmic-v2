import { Link } from "@inertiajs/react"

export default function AccessDenied() {
    return (
        <div
            style={{
                minHeight: "100dvh",
                background: "#000",
                display: "flex",
                flexDirection: "column",
                alignItems: "center",
                justifyContent: "center",
                padding: "24px",
                overflow: "hidden",
            }}
        >
            <div style={{ display: "flex", alignItems: "center", width: "100%", padding: "0 24px" }}>
                <div style={{ position: "relative", fontFamily: "'BitcountGridDouble'", fontSize: 20, color: "#fff" }}>
                    <span style={{ position: "relative", zIndex: 10 }}>The Cosmic</span>
                    <img
                        src="/black-hole.jpg"
                        alt=""
                        style={{ position: "absolute", left: "90%", top: "-5px", width: 62, transform: "rotate(30deg)" }}
                    />
                </div>
            </div>
            <div style={{ maxWidth: 420, width: "100%", textAlign: "center" }}>
                {/* Illustration */}
                <div
                    style={{
                        position: "relative",
                        width: 600,
                        maxWidth: "120vw",
                        margin: "0 auto 32px",
                        transform: "translateX(-30px)",
                    }}
                >
                    <img
                        src="/lv_0_20260622201200.jpg"
                        alt="Access Denied"
                        style={{ width: "100%", display: "block" }}
                    />
                    <div
                        style={{
                            position: "absolute",
                            inset: 0,
                            pointerEvents: "none",
                            background:
                                "linear-gradient(to right, #000 0%, transparent 10%, transparent 90%, #000 100%)",
                        }}
                    />
                </div>

                {/* Buttons */}
                <div style={{ display: "flex", gap: 12, justifyContent: "center", flexWrap: "wrap" }}>
                    <Link
                        href="/"
                        style={{
                            display: "inline-flex",
                            alignItems: "center",
                            padding: "10px 24px",
                            fontSize: 13,
                            fontWeight: 700,
                            color: "#000",
                            background: "#fff",
                            textDecoration: "none",
                            transition: "opacity 0.2s",
                        }}
                        onMouseEnter={(e) => (e.currentTarget.style.opacity = "0.8")}
                        onMouseLeave={(e) => (e.currentTarget.style.opacity = "1")}
                    >
                        Kembali ke Beranda
                    </Link>
                    <Link
                        href="/contact"
                        style={{
                            display: "inline-flex",
                            alignItems: "center",
                            padding: "10px 24px",
                            fontSize: 13,
                            fontWeight: 700,
                            color: "#999",
                            background: "#111",
                            border: "1px solid #2A2A2A",
                            textDecoration: "none",
                            transition: "all 0.2s",
                        }}
                        onMouseEnter={(e) => {
                            e.currentTarget.style.color = "#fff"
                            e.currentTarget.style.borderColor = "#fff"
                        }}
                        onMouseLeave={(e) => {
                            e.currentTarget.style.color = "#999"
                            e.currentTarget.style.borderColor = "#2A2A2A"
                        }}
                    >
                        Hubungi Kami
                    </Link>
                </div>
            </div>
        </div>
    )
}
