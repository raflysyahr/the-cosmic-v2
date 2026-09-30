import { useModal } from '../../contexts/ModalContext'

import { usePopup } from '../../contexts/PopupContext'

// Aset frame modal detail realm — lihat storage/app/public/modal/*.png,
// disajikan lewat symlink public/storage (php artisan storage:link).
const modalAsset = (file) => `/storage/modal/${file}`

export default function Crest({ realm,size }){

    const { modal,showModal,closeModal} = useModal()
    const { popup,showPopup,closePopup} = usePopup()

    const showDetail = ()=>{

        showModal({
              size: 'lg', // sm | md | lg | xl | full
              bare: true, // lepas dari chrome default Modal (rounded-full/border/bg gelap) — frame di bawah ini yang jadi background & border-nya
              content: (
                    <div
                        className="relative flex w-full flex-col items-center gap-1 px-8 py-10"
                        style={{
                            // Multiple background layers dalam satu elemen: layer PERTAMA
                            // digambar PALING DEPAN, layer berikutnya di belakangnya.
                            // border-frame di depan, di-stretch 100% 100% supaya PAS
                            // mengisi box (ini yang mendefinisikan tepi border-nya).
                            // background-frame di belakang pakai 'contain' + posisi
                            // center — 'contain' menyusutkan gambar secukupnya supaya
                            // MUAT SELURUHNYA di dalam box tanpa pernah kepotong/keluar
                            // (beda dari 100% 100% yang maksa stretch dan bisa bikin
                            // sebagian gambar terlihat "melewati" tepi border kalau
                            // rasio aslinya beda dari border). Ini menjamin background
                            // tidak akan pernah melewati border, di sisi manapun.
                            backgroundImage: `url(${modalAsset('border-frame.png')}), url(${modalAsset('background-frame.png')})`,
                            backgroundSize: '100% 100%, 90% 90%',
                            backgroundRepeat: 'no-repeat, no-repeat',
                            backgroundPosition: 'center, center',
                        }}
                    >

                        {/* Avatar realm + border-avatar-badge di atasnya.
                            Avatar di-scale ke 72% mengikuti pola yang sama dengan
                            Avatar.tsx (avatar-border.png) — supaya cincin dekoratif
                            di border-avatar-badge.png tidak menutupi/kepotong badge. */}
                        <div className="relative flex mt-10 items-center justify-center" style={{ width: 96, height: 96 }}>
                            <div className="h-full w-full scale-[1] translate-x-[-1px] overflow-hidden rounded-full absolute z-[999]">
                                <img
                                    className="h-full w-full object-cover"
                                    src={`/api/cultivation/realm-badge/${realm.realm_slug}`}
                                    alt={realm.realm_name}
                                />
                            </div>
                            <img
                                src={modalAsset('border-avatar-badge.png')}
                                alt=""
                                className="pointer-events-none absolute  z-1 w-full h-full scale-[2] select-none object-contain"
                            />
                        </div>

                        <h1 className="mt-2 text-lg font-semibold text-white">{realm.realm_name}</h1>
                        <p className="text-xs text-center text-white/60"
                        >Era of {realm.era_name ?? '—'}</p>

                        {/* Nameplate sumber energi era (mis. Essence/Astrum/Cosmos) */}
                        <div className="relative mt-1 flex items-center justify-center">
                            <img
                                src={modalAsset('display-source-realm.png')}
                                alt=""
                                className="pointer-events-none absolute inset-0 -z-10 h-full w-full select-none object-fill"
                            />
                            <span className="px-6 py-1 w-[250px] h-[40px] text-[10px] uppercase tracking-wide text-white/80 flex relative overflow-hidden items-center justify-center ">
                            <div className="h-[70px] translate-y-[-2px] bg-center bg-contain w-[250px] absolute"
                            style={{ backgroundImage:'url("/storage/modal/display-source-realm.png")',backgroundPosition:"center"}}
                            />
                                <p>{realm.source_name ?? '—'}</p>
                            </span>
                        </div>

                        {/* Divider pemisah header (avatar+nama+source) dari deskripsi */}






                        <p className="mt-4 text-center text-sm leading-relaxed text-white/70 h-[170px] scrollbar-none overflow-y-scroll">
                            {realm.description ?? 'Belum ada deskripsi untuk realm ini.'}
                        </p>
                        <img
                        src={modalAsset('divider.png')}
                        alt=""
                        className="mt-4 h-auto w-3/4 select-none object-contain"
                        />
                </div>
            ),
        })
    }


    return (
        <div className={` relative rounded-[20px] overflow-hidden items-center flex justify-center`}
        onClick={showDetail}
        style={{ width: `${size}px`, height: `${size}px` }}>
            <img className="w-full h-full object-cover rendering-pixelated" src={`/api/cultivation/realm-badge/${realm.realm_slug}`} alt={realm.realm_name}/>
        </div>
    )
}
