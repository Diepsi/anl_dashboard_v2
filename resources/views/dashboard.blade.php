<x-app-layout>

<div x-data="introController()">

    <style>[x-cloak]{display:none!important}</style>

    {{-- Splash intro --}}
    <div x-show="splash"
         x-cloak
         x-transition.opacity.duration.300ms
         class="intro-splash fixed inset-0 z-[60] flex flex-col items-center justify-center gap-5 bg-surface">
        <button @click="skip()"
            class="absolute top-5 right-5 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-muted hover:bg-surface-subtle hover:text-ink dark:hover:bg-surface-subtle dark:hover:text-ink transition"
            aria-label="Lewati animasi">
            Lewati
            <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
        </button>

        <div class="flex items-center gap-4">
            <div class="intro-logo p-4 rounded-2xl bg-cyan-600/10 dark:bg-cyan-950/80">
                <svg class="w-14 h-14 text-cyan-600 dark:text-cyan-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z" />
                </svg>
            </div>
            <div>
                <p class="text-2xl font-bold text-ink">ANL Dashboard V2</p>
                <p class="text-sm text-muted">Operasional Pengiriman</p>
            </div>
        </div>

        <div class="intro-progress intro-track h-1.5 rounded-full bg-surface-subtle overflow-hidden">
            <div class="intro-progress-bar h-full rounded-full bg-cyan-600 dark:bg-brand"></div>
        </div>

        <p class="text-xs text-muted">Memuat data pengiriman...</p>
    </div>

    <div class="p-4 sm:p-6 lg:p-8 space-y-6 bg-canvas" x-data="modalController()">

    {{-- Header --}}
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-ink">Dashboard Operasional</h1>
            <p class="mt-1 text-sm text-muted">
                @if ($range === 0)
                    semua periode @else {{ $range }} hari terakhir data
                @endif
                @if ($vendor)
                    · vendor <span class="font-semibold text-ink">{{ $vendor }}</span>
                @endif
                @if ($latestManifest)
                    (data: {{ $earliestManifest?->format('d M Y') }} – {{ $latestManifest?->format('d M Y') }})
                @endif
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <div class="flex items-center gap-1 p-1 rounded-lg bg-surface border border-border">
                @foreach ([0 => 'All', 30 => '30 Hari', 90 => '90 Hari'] as $r => $label)
                    <a href="{{ request()->fullUrlWithQuery(['range' => $r]) }}"
                       class="px-3 py-1.5 rounded-md text-sm font-semibold transition
                              {{ $range === $r ? 'bg-cyan-600 text-white shadow dark:bg-brand dark:text-slate-950' : 'text-muted hover:bg-surface-subtle dark:hover:bg-surface-subtle dark:hover:text-ink' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
            <form method="GET" action="{{ route('dashboard') }}" class="flex items-center gap-2">
                <input type="hidden" name="range" value="{{ $range }}">
                <label for="vendor-filter" class="sr-only">Vendor LM</label>
                <select id="vendor-filter" name="vendor" onchange="this.form.submit()"
                    class="px-3 py-1.5 rounded-lg text-sm font-semibold border border-border dark:border-slate-500 bg-surface dark:bg-slate-800 text-ink dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500/40">
                    <option value="">Semua Vendor</option>
                    @foreach ($vendors as $vendorName => $vendorTotal)
                        <option value="{{ $vendorName }}" @selected($vendor === $vendorName)>
                            {{ $vendorName }} ({{ number_format($vendorTotal, 0, ',', '.') }})
                        </option>
                    @endforeach
                </select>
                @if ($vendor)
                    <a href="{{ request()->fullUrlWithQuery(['vendor' => null]) }}"
                        class="px-2.5 py-1.5 rounded-lg text-xs font-semibold text-muted border border-border hover:bg-surface-subtle hover:text-ink transition">
                        Reset
                    </a>
                @endif
            </form>
            @if (auth()->user()?->isAdmin())
                <form method="POST" action="{{ route('sync') }}">
                    @csrf
                    <button type="submit"
                        class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-semibold text-cyan-700 dark:text-cyan-300 bg-cyan-50 dark:bg-cyan-950/80 border border-cyan-200 dark:border-cyan-800 hover:bg-cyan-100 dark:hover:bg-cyan-900 transition">
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                        Sync Sekarang
                    </button>
                </form>
            @endif
            <div class="px-3 py-1.5 rounded-lg text-xs text-muted bg-surface border border-border">
                Terakhir sync:
                <span class="font-semibold text-ink">{{ $lastSync?->format('d M Y H:i') ?? '—' }}</span>
            </div>
        </div>
    </div>

    @if (session('status'))
        <div class="p-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 dark:bg-emerald-950/80 dark:border-emerald-800 dark:text-emerald-300 text-sm">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->has('sync'))
        <div class="p-4 rounded-lg bg-red-50 border border-red-200 text-red-700 dark:bg-red-950/80 dark:border-red-800 dark:text-red-300 text-sm">
            {{ $errors->first('sync') }}
        </div>
    @endif

    @if ($sourceStats)
        @php
            $validRows = (int) $sourceStats['valid_rows'];
            $dupExtra = (int) $sourceStats['dup_extra'];
            $duplicates = $sourceStats['duplicates'] ?? [];
            $diff = $validRows - $dbTotal;
        @endphp
        <div class="bg-surface rounded-xl shadow-sm border border-border p-4">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                <div class="p-2 rounded-lg bg-blue-800/10 dark:bg-blue-950/80">
                    <svg class="w-5 h-5 text-blue-800 dark:text-blue-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
                    </svg>
                </div>
                <div class="text-sm text-muted">
                    Sumber GSheet: <span class="font-bold text-ink">{{ number_format($validRows, 0, ',', '.') }}</span> resi
                    <span class="mx-1 text-muted">·</span>
                    Database: <span class="font-bold text-ink">{{ number_format($dbTotal, 0, ',', '.') }}</span> resi
                </div>
                @if ($diff === 0)
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700 dark:bg-emerald-950/80 dark:text-emerald-300">
                        <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                        Sesuai
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-700 dark:bg-amber-950/80 dark:text-amber-300">
                        <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                        </svg>
                        Selisih {{ number_format(abs($diff), 0, ',', '.') }}
                    </span>
                @endif
            </div>
            @php
                $resiInvalid = (int) ($sourceStats['resi_no_fungsi'] ?? 0)
                    + (int) ($sourceStats['resi_nomor_rusak'] ?? 0)
                    + (int) ($sourceStats['resi_format'] ?? 0)
                    + (int) ($sourceStats['resi_kosong'] ?? 0);
                $resiDuplicate = (int) ($sourceStats['resi_duplikat'] ?? 0);
                $dupTotalRows = (int) ($sourceStats['dup_total_rows'] ?? 0);
                $dupKeys = (int) ($sourceStats['dup_keys'] ?? 0);
                $skippedEmpty = (int) ($sourceStats['skipped_empty'] ?? 0);
                // resi_catatan hanya menyimpan satu label, jadi kategori
                // "bermasalah" menang atas "Duplikat".
                $dupInvalid = max(0, $dupTotalRows - $resiDuplicate);
            @endphp
            @if ($resiInvalid > 0 || $resiDuplicate > 0 || $dupInvalid > 0)
                <p class="mt-2 text-xs text-muted leading-relaxed">
                    Seluruh {{ number_format($validRows, 0, ',', '.') }} baris data sumber tersimpan apa adanya, termasuk yang bermasalah — tidak ada baris berisi data yang dibuang.
                    @if ($skippedEmpty > 0)
                        <span class="font-semibold text-slate-500 dark:text-slate-400">{{ number_format($skippedEmpty, 0, ',', '.') }} baris kosong</span>
                        (tanpa <code class="text-[11px]">No Resi</code> &amp; <code class="text-[11px]">NAMA SEKOLAH</code> — sisa formula di sheet) tidak dihitung ·
                    @endif
                    @if ($resiInvalid > 0)
                        <span class="font-semibold text-red-700 dark:text-red-300">{{ number_format($resiInvalid, 0, ',', '.') }} resi bermasalah</span>
                        (<code class="text-[11px]">#N/A</code>, scientific notation, atau format tidak valid)
                        ditandai <span class="font-semibold text-amber-700 dark:text-amber-300">Resi bermasalah</span>
                        di <a href="{{ route('shipments', ['resi' => 'invalid']) }}" class="underline hover:text-ink">Data Pengiriman</a> ·
                        @endif
                    @if ($resiDuplicate > 0 || $dupInvalid > 0)
                        <span class="font-semibold text-amber-700 dark:text-amber-300">{{ number_format($dupTotalRows, 0, ',', '.') }} baris</span>
                        ({{ $dupKeys }} nomor resi) memakai nomor resi yang sama dengan baris lain.
                        {{ number_format($resiDuplicate, 0, ',', '.') }} di antaranya berformat valid dan ditandai
                        <span class="font-semibold text-amber-700 dark:text-amber-300">Duplikat</span>, sedangkan
                        {{ number_format($dupInvalid, 0, ',', '.') }} sisanya sudah termasuk
                        <span class="font-semibold text-red-700 dark:text-red-300">Resi bermasalah</span> ·
                        @endif
                    <span class="text-slate-400 dark:text-slate-500">Nomor resi sumber yang rusak perlu diperbaiki di GSheet agar dapat dibaca.</span>
                </p>
            @endif
            @if (! empty($duplicates))
                <details class="mt-2 group">
                    <summary class="text-xs font-semibold text-cyan-700 dark:text-cyan-300 cursor-pointer hover:text-cyan-800 dark:hover:text-cyan-300 select-none">
                        Lihat nomor resi duplikat ({{ count($duplicates) }} nomor, {{ $dupExtra }} baris ekstra)
                    </summary>
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        @foreach ($duplicates as $noResi => $muncul)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-amber-50 border border-amber-200 text-[11px] font-mono text-amber-800 dark:bg-amber-950/80 dark:border-amber-800 dark:text-amber-300">
                                {{ $noResi }}
                                <span class="text-amber-500 dark:text-amber-300 font-sans">×{{ $muncul }}</span>
                            </span>
                        @endforeach
                    </div>
                </details>
            @endif
        </div>
    @endif

    {{-- KPI Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-4 xl:gap-5 stagger-ready">

        {{-- Total Shipments --}}
        <div class="bg-surface rounded-xl shadow-sm border border-border border-t-4 border-t-blue-800 dark:border-t-blue-400 p-5">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs font-semibold tracking-wider text-muted uppercase">Total Shipments</p>
                    <p class="mt-2 text-3xl font-bold text-ink">{{ number_format($totalShipment, 0, ',', '.') }}</p>
                    <p class="mt-1 text-xs text-muted">Total keseluruhan resi</p>
                    <div class="mt-3">
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-muted font-medium">Completion</span>
                            <span class="font-bold text-emerald-600 dark:text-emerald-300">{{ $completionRate }}%</span>
                        </div>
                        <div class="mt-1 h-1.5 rounded-full bg-surface-subtle overflow-hidden">
                            <div class="h-full rounded-full bg-emerald-500 dark:bg-emerald-400 transition-all" style="width: {{ $completionRate }}%"></div>
                        </div>
                    </div>
                </div>
                <div class="p-2.5 rounded-lg bg-blue-800/10 dark:bg-blue-950/80 shrink-0">
                    <svg class="w-6 h-6 text-blue-800 dark:text-blue-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 6.878V6a2.25 2.25 0 0 1 2.25-2.25h7.5A2.25 2.25 0 0 1 18 6v.878m-12 0c.235-.083.487-.128.75-.128h10.5c.263 0 .515.045.75.128m-12 0A2.25 2.25 0 0 0 4.5 9v.878m13.5-3A2.25 2.25 0 0 1 19.5 9v.878m0 0a2.246 2.246 0 0 0-.75-.128H5.25c-.263 0-.515.045-.75.128m13.5 0a2.246 2.246 0 0 1 0 .628m0 0a2.25 2.25 0 0 1-.75.128M6 13.5h12m-9 3h6" />
                    </svg>
                </div>
            </div>
        </div>

        {{-- Completed --}}
        <div class="bg-surface rounded-xl shadow-sm border border-border border-t-4 border-t-green-500 dark:border-t-green-400 p-5">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs font-semibold tracking-wider text-muted uppercase">Completed</p>
                    <p class="mt-2 text-3xl font-bold text-ink">{{ number_format($completed, 0, ',', '.') }}</p>
                    <p class="mt-1 text-xs text-muted">Telah sampai di tujuan</p>
                </div>
                <div class="p-2.5 rounded-lg bg-green-500/10 dark:bg-green-950/80 shrink-0">
                    <svg class="w-6 h-6 text-green-600 dark:text-green-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
            </div>
        </div>

        {{-- On Delivery --}}
        <div class="bg-surface rounded-xl shadow-sm border border-border border-t-4 border-t-blue-500 dark:border-t-blue-400 p-5">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs font-semibold tracking-wider text-muted uppercase">On Delivery</p>
                    <p class="mt-2 text-3xl font-bold text-ink">{{ number_format($onDeliveryCount, 0, ',', '.') }}</p>
                    <p class="mt-1 text-xs text-muted">Sedang dikirim ke tujuan</p>
                </div>
                <div class="p-2.5 rounded-lg bg-blue-500/10 dark:bg-blue-950/80 shrink-0">
                    <svg class="w-6 h-6 text-blue-600 dark:text-blue-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
                    </svg>
                </div>
            </div>
        </div>

        {{-- Hold --}}
        <div class="bg-surface rounded-xl shadow-sm border border-border border-t-4 border-t-orange-500 dark:border-t-orange-400 p-5">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs font-semibold tracking-wider text-muted uppercase">Hold</p>
                    <p class="mt-2 text-3xl font-bold text-ink">{{ number_format($holdCount, 0, ',', '.') }}</p>
                    <p class="mt-1 text-xs text-muted">Ditahan / menunggu follow-up</p>
                </div>
                <div class="p-2.5 rounded-lg bg-orange-500/10 dark:bg-orange-950/80 shrink-0">
                    <svg class="w-6 h-6 text-orange-500 dark:text-orange-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12v-.008Zm0-6.75h.008v.008H12V9.75Zm1.35-4.5h8.7a.75.75 0 0 1 .75.75v8.7a.75.75 0 0 1-.22.53l-8.7 8.7a.75.75 0 0 1-1.06 0l-8.7-8.7a.75.75 0 0 1-.22-.53v-8.7a.75.75 0 0 1 .75-.75h8.7Z" />
                    </svg>
                </div>
            </div>
        </div>

        {{-- Undelivered --}}
        <div class="bg-surface rounded-xl shadow-sm border border-border border-t-4 border-t-red-500 dark:border-t-red-400 p-5">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs font-semibold tracking-wider text-muted uppercase">Undelivered</p>
                    <p class="mt-2 text-3xl font-bold text-ink">{{ number_format($undeliveredCount, 0, ',', '.') }}</p>
                    <p class="mt-1 text-xs text-muted">Gagal terkirim / kembali</p>
                    <p class="mt-1 text-xs font-bold text-red-600 dark:text-red-300">{{ $undeliveredRate }}% dari total kiriman</p>
                </div>
                <div class="p-2.5 rounded-lg bg-red-500/10 dark:bg-red-950/80 shrink-0">
                    <svg class="w-6 h-6 text-red-600 dark:text-red-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                    </svg>
                </div>
            </div>
        </div>

        {{-- Within SLA --}}
        <div class="bg-surface rounded-xl shadow-sm border border-border border-t-4 border-t-emerald-500 dark:border-t-emerald-400 p-5">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs font-semibold tracking-wider text-muted uppercase">Within SLA</p>
                    <p class="mt-2 text-3xl font-bold text-ink">{{ number_format($withinSla, 0, ',', '.') }}</p>
                    <p class="mt-1 text-xs text-muted">Tepat &amp; sesuai SLA — DB Logistics</p>
                </div>
                <div class="p-2.5 rounded-lg bg-emerald-500/10 dark:bg-emerald-950/80 shrink-0">
                    <svg class="w-6 h-6 text-emerald-600 dark:text-emerald-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
            </div>
        </div>

        {{-- Over SLA --}}
        <div class="bg-surface rounded-xl shadow-sm border border-border border-t-4 border-t-red-600 dark:border-t-red-400 p-5">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs font-semibold tracking-wider text-muted uppercase">Over SLA (DB Logistics)</p>
                    <p class="mt-2 text-3xl font-bold text-ink">{{ number_format($overSla, 0, ',', '.') }}</p>
                    <p class="mt-1 text-xs text-muted">
                        Berjalan <span class="font-semibold text-red-600 dark:text-red-300">{{ number_format($outSlaActive) }}</span>
                        · selesai <span class="font-semibold">{{ number_format($outSlaDone) }}</span>
                    </p>
                </div>
                <div class="p-2.5 rounded-lg bg-red-600/10 dark:bg-red-950/80 shrink-0">
                    <svg class="w-6 h-6 text-red-600 dark:text-red-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                    </svg>
                </div>
            </div>
        </div>

        {{-- SLA Ach. Rate --}}
        <div class="bg-surface rounded-xl shadow-sm border border-border border-t-4 border-t-indigo-500 dark:border-t-indigo-400 p-5">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs font-semibold tracking-wider text-muted uppercase">SLA &amp; Durasi (Khusus DB Logistics)</p>
                    <p class="mt-2 text-3xl font-bold text-ink">{{ $slaPct }}<span class="text-lg text-muted font-semibold">%</span></p>
                    <p class="mt-1 text-xs text-muted">
                        Meet <span class="font-semibold text-emerald-600 dark:text-emerald-300">{{ number_format($withinSla) }}</span>
                        · Over <span class="font-semibold text-red-600 dark:text-red-300">{{ number_format($overSla) }}</span>
                    </p>
                    <p class="mt-1 text-xs text-muted">
                        dari <span class="font-semibold">{{ number_format($leadTimeScope) }}</span>
                        kiriman ber-Tanggal HO
                    </p>
                </div>
                <div class="p-2.5 rounded-lg bg-indigo-500/10 dark:bg-indigo-950/80 shrink-0">
                    <svg class="w-6 h-6 text-indigo-600 dark:text-indigo-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                    </svg>
                </div>
            </div>
        </div>

        {{-- Rata-rata Durasi --}}
        <div class="bg-surface rounded-xl shadow-sm border border-border border-t-4 border-t-cyan-600 dark:border-t-cyan-400 p-5">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs font-semibold tracking-wider text-muted uppercase">SLA &amp; Durasi (Khusus DB Logistics)</p>
                    <p class="mt-2 text-3xl font-bold text-ink">
                        @if ($avgLeadTime > 0)
                            {{ number_format($avgLeadTime, 1, ',', '.') }}<span class="text-lg text-muted font-semibold"> hari</span>
                        @else
                            <span class="text-2xl text-muted">—</span>
                        @endif
                    </p>
                    <p class="mt-1 text-xs text-muted">Manifest → selesai (Completed), hanya baris ber-Tanggal HO</p>
                </div>
                <div class="p-2.5 rounded-lg bg-cyan-600/10 dark:bg-cyan-950/80 shrink-0">
                    <svg class="w-6 h-6 text-cyan-600 dark:text-cyan-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
            </div>
        </div>
    </div>

    {{-- Combo Chart --}}
    <div id="tren-pengiriman" class="bg-surface rounded-xl shadow-sm border border-border p-6 stagger-ready">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
            <div>
                <h2 class="text-lg font-bold text-ink">Tren Pengiriman Daily</h2>
                <p class="text-sm text-muted">Volume kirim, selesai, & kepatuhan SLA per hari</p>
            </div>
            <div class="flex items-center gap-1 p-1 rounded-lg bg-surface-subtle">
                @foreach ([0 => 'All', 30 => '30 Hari', 90 => '90 Hari'] as $r => $label)
                    <a href="{{ request()->fullUrlWithQuery(['range' => $r]) }}"
                       class="px-3 py-1.5 rounded-md text-xs font-semibold transition
                              {{ $range === $r ? 'bg-surface text-ink shadow' : 'text-muted hover:text-ink dark:hover:text-ink' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </div>

        <div class="h-[380px]">
            <canvas id="trendChart"></canvas>
        </div>
    </div>

    {{-- Analytics doughnuts --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5 stagger-ready">

        {{-- Status Pengiriman --}}
        <div class="bg-surface rounded-xl shadow-sm border border-border p-6">
            <div class="mb-4">
                <h2 class="text-lg font-bold text-ink">Status Pengiriman</h2>
                <p class="text-sm text-muted">Komposisi status akhir saat ini</p>
            </div>
            <div class="relative h-[260px]">
                <canvas id="statusChart"></canvas>
            </div>
        </div>

        {{-- SLA Compliance Rate --}}
        <div class="bg-surface rounded-xl shadow-sm border border-border p-6">
            <div class="mb-4">
<h2 class="text-lg font-bold text-ink">SLA Compliance Rate</h2>
            <p class="text-sm text-muted">Tingkat pemenuhan SLA pada baris ber-Tanggal HO (DB Logistics)</p>
            </div>
            <div class="relative h-[260px]">
                <canvas id="slaChart"></canvas>
                <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                    <div class="text-center">
                        <p class="text-3xl font-bold text-ink">{{ $slaPct }}<span class="text-base text-muted">%</span></p>
                        <p class="text-xs text-muted font-medium">Meet SLA</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- BAST Handover Finance --}}
        <div class="bg-surface rounded-xl shadow-sm border border-border p-6">
            <div class="mb-4">
                <h2 class="text-lg font-bold text-ink">BAST Handover Finance</h2>
                <p class="text-sm text-muted">BAST yang telah diserahkan ke finance</p>
            </div>
            <div class="relative h-[260px]">
                <canvas id="financeChart"></canvas>
            </div>
        </div>
    </div>

    {{-- Top 5 charts row --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 stagger-ready">

        {{-- Top 5 Provinsi --}}
        <div class="bg-surface rounded-xl shadow-sm border border-border p-6">
            <div class="mb-4">
                <h2 class="text-lg font-bold text-ink">Top 5 Provinsi Tujuan</h2>
                <p class="text-sm text-muted">5 provinsi dengan pengiriman terbanyak saat ini</p>
            </div>
            <div class="h-[320px]">
                <canvas id="provinsiChart"></canvas>
            </div>
        </div>

        {{-- Top Vendor --}}
        <div class="bg-surface rounded-xl shadow-sm border border-border p-6">
            <div class="mb-4">
                <h2 class="text-lg font-bold text-ink">Top Vendor</h2>
                <p class="text-sm text-muted">Vendor logistik laut — 5 teratas + lainnya</p>
            </div>
            <div class="h-[320px]">
                <canvas id="vendorChart"></canvas>
            </div>
        </div>
    </div>

    {{-- Kinerja Vendor --}}
    <div class="bg-surface rounded-xl shadow-sm border border-border overflow-hidden stagger-ready">
        <div class="px-6 py-5 border-b border-border">
            <h2 class="text-lg font-bold text-ink">Kinerja Vendor</h2>
            <p class="text-sm text-muted">5 vendor teratas — kualitas on-time (SLA), rata-rata durasi kirim, &amp; aging. Metrik SLA &amp; durasi hanya menghitung baris ber-Tanggal HO, yaitu DB Logistics.</p>
        </div>
        <div class="overflow-x-auto dark:bg-surface">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="bg-surface-subtle text-xs uppercase tracking-wider text-muted border-b border-border">
                        <th class="px-6 py-3 font-semibold">Vendor LM</th>
                        <th class="px-6 py-3 font-semibold">Total Resi</th>
                        <th class="px-6 py-3 font-semibold">Resi Terukur</th>
                        <th class="px-6 py-3 font-semibold">On-Time (SLA)</th>
                        <th class="px-6 py-3 font-semibold">Rata-rata Durasi</th>
                        <th class="px-6 py-3 font-semibold">Rata-rata Aging</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border/70">
                    @forelse ($vendorStats as $v)
                        <tr class="hover:bg-surface-subtle transition">
                            <td class="px-6 py-3.5 font-semibold text-ink">{{ $v['vendor'] }}</td>
                            <td class="px-6 py-3.5 text-muted">{{ number_format($v['total_keseluruhan'], 0, ',', '.') }}</td>
                            <td class="px-6 py-3.5 text-muted">
                                {{ number_format($v['total_terukur'], 0, ',', '.') }}
                                <span class="text-xs text-muted">({{ $v['terukur_pct'] }}%)</span>
                            </td>
                            <td class="px-6 py-3.5">
                                <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold
                                    {{ $v['onTimePct'] >= 80 ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/80 dark:text-emerald-300' : ($v['onTimePct'] >= 60 ? 'bg-amber-100 text-amber-700 dark:bg-amber-950/80 dark:text-amber-300' : 'bg-red-100 text-red-700 dark:bg-red-950/80 dark:text-red-300') }}">
                                    {{ $v['onTimePct'] }}%
                                </span>
                            </td>
                            <td class="px-6 py-3.5 text-muted">{{ $v['avgLead'] !== null ? number_format($v['avgLead'], 1, ',', '.') . ' hari' : '—' }}</td>
                            <td class="px-6 py-3.5 text-muted">{{ $v['avgAging'] !== null ? number_format($v['avgAging'], 1, ',', '.') . ' hari' : '—' }}</td>
                        </tr>
                    @empty
<tr>
                                <td colspan="6" class="px-6 py-10 text-center text-muted">Belum ada data vendor.</td>
                            </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Middle row: Stagging + Bottleneck --}}
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-5 stagger-ready">

        {{-- Stagging Pipeline --}}
        <div id="stagging-pengiriman" class="bg-surface rounded-xl shadow-sm border border-border p-6">
            <div class="mb-4">
                <h2 class="text-lg font-bold text-ink">Stagging Pengiriman</h2>
                <p class="text-sm text-muted">Posisi pengiriman saat ini — klik tahap untuk detail</p>
            </div>

            @if ($totalShipment > 0 && $staggingFilled < $totalShipment)
                <p class="mb-4 px-3 py-2 rounded-lg bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-900 text-xs text-amber-800 dark:text-amber-300 leading-relaxed">
                    Kolom stagging hanya terisi pada
                    <span class="font-semibold">{{ number_format($staggingFilled, 0, ',', '.') }}</span>
                    dari <span class="font-semibold">{{ number_format($totalShipment, 0, ',', '.') }}</span>
                    baris ({{ round($staggingFilled / $totalShipment * 100, 1) }}%) pada sumber. Tahap di bawah hanya mewakili baris yang sudah punya data stagging.
                </p>
            @endif

            <div class="space-y-2">
                @forelse ($staggingList as $stage)
                    <button
                        class="w-full flex items-center gap-3 px-4 py-3 rounded-xl border border-border hover:border-cyan-400 dark:hover:border-cyan-300 hover:bg-cyan-50/50 dark:hover:bg-cyan-950/40 transition group text-left"
                        @click="openStaging($el.dataset.stage, $el.dataset.total)"
                        data-stage="{{ $stage['name'] }}" data-total="{{ $stage['total'] }}">
                        <span class="w-2.5 h-2.5 rounded-full bg-cyan-500 dark:bg-cyan-400 shrink-0"></span>
                        <span class="flex-1 min-w-0">
                            <span class="block text-sm font-semibold text-ink group-hover:text-cyan-800 dark:group-hover:text-cyan-300">{{ $stage['name'] }}</span>
                            <span class="block text-xs text-muted">Klik untuk melihat detail resi</span>
                        </span>
                        <span class="px-2.5 py-1 rounded-lg bg-cyan-50 dark:bg-cyan-950/80 text-cyan-700 dark:text-cyan-300 text-sm font-bold">{{ number_format($stage['total']) }}</span>
                        <svg class="w-4 h-4 text-muted group-hover:text-cyan-500 dark:group-hover:text-cyan-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 12h10.5m0 0-3-3m3 3-3 3" />
                        </svg>
                    </button>
                @empty
                    <p class="text-sm text-muted">Belum ada data stagging.</p>
                @endforelse
            </div>
        </div>

        {{-- Bottleneck Over SLA --}}
        <div id="bottleneck-wide" class="bg-surface rounded-xl shadow-sm border border-border p-6">
            <div class="mb-4">
                <h2 class="text-lg font-bold text-ink">Bottleneck Wilayah Over SLA</h2>
                <p class="text-sm text-muted">Provinsi dengan pengiriman melebihi SLA pada baris ber-Tanggal HO — klik untuk detail</p>
            </div>

            @php
                $maxBottleneck = $bottleneckStats->max('total') ?: 1;
            @endphp

            <div class="space-y-3">
                @forelse ($bottleneckStats as $b)
                    <button
                        class="w-full group text-left"
                        @click="openBottleneck($el.dataset.provinsi, $el.dataset.total)"
                        data-provinsi="{{ $b->provinsi }}" data-total="{{ $b->total }}">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-sm font-semibold text-ink group-hover:text-red-700 dark:group-hover:text-red-300">{{ $b->provinsi }}</span>
                            <span class="text-sm font-bold text-red-600 dark:text-red-300">{{ number_format($b->total) }}</span>
                        </div>
                        <div class="h-2 rounded-full bg-surface-subtle overflow-hidden">
                            <div class="h-full rounded-full bg-gradient-to-r from-amber-400 to-red-500 transition group-hover:from-amber-500 group-hover:to-red-600 dark:from-amber-500 dark:to-red-400 dark:group-hover:from-amber-400 dark:group-hover:to-red-300"
                                 style="width: {{ ($b->total / $maxBottleneck) * 100 }}%"></div>
                        </div>
                        <p class="mt-1 text-[11px] text-muted">Klik untuk melihat detail resi</p>
                    </button>
                @empty
                    <p class="text-sm text-muted">Tidak ada data Over SLA pada filter ini.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Perlu Perhatian --}}
    <div class="bg-surface rounded-xl shadow-sm border border-border p-6 stagger-ready">
        <div class="mb-4">
            <h2 class="text-lg font-bold text-ink">Perlu Perhatian</h2>
            <p class="text-sm text-muted">Pengiriman belum selesai yang perlu follow-up — usia ≥ 14 hari atau berstatus Hold/Undelivered</p>
        </div>

        @php
            $agingTotal = $agingBuckets->sum();
            $maxBucket = $agingBuckets->max() ?: 1;
        @endphp

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            @foreach ($agingBuckets as $label => $total)
                <div class="rounded-xl border {{ $label === '> 30 hari' ? 'border-red-200 bg-red-50/60 dark:border-red-800 dark:bg-red-950/60' : 'border-border bg-surface-subtle/60' }} p-4">
                    <p class="text-xs font-semibold tracking-wider {{ $label === '> 30 hari' ? 'text-red-600 dark:text-red-300' : 'text-muted' }} uppercase">{{ $label }}</p>
                    <p class="mt-1 text-2xl font-bold {{ $label === '> 30 hari' ? 'text-red-700 dark:text-red-300' : 'text-ink' }}">{{ number_format($total) }}</p>
                    <div class="mt-2 h-1.5 rounded-full bg-border/70 overflow-hidden">
                        <div class="h-full rounded-full {{ $label === '> 30 hari' ? 'bg-red-400 dark:bg-red-300' : 'bg-slate-400 dark:bg-slate-300' }}"
                             style="width: {{ ($total / max($agingTotal, 1)) * 100 }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($attentionShipments->isNotEmpty())
            <div class="mt-4 overflow-x-auto dark:bg-surface">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="bg-surface-subtle text-xs uppercase tracking-wider text-muted border-b border-border">
                            <th class="px-4 py-3 font-semibold">No Resi</th>
                            <th class="px-4 py-3 font-semibold">Sekolah</th>
                            <th class="px-4 py-3 font-semibold">Provinsi</th>
                            <th class="px-4 py-3 font-semibold">Status</th>
                            <th class="px-4 py-3 font-semibold">Stagging</th>
                            <th class="px-4 py-3 font-semibold">Usia</th>
                            <th class="px-4 py-3 font-semibold">SLA</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/70">
                        @foreach ($attentionShipments as $ship)
                            <tr class="hover:bg-surface-subtle transition">
                                <td class="px-4 py-3 font-mono text-ink whitespace-nowrap">{{ $ship->no_resi }}</td>
                                <td class="px-4 py-3 text-ink font-medium max-w-[200px] truncate">{{ $ship->nama_sekolah }}</td>
                                <td class="px-4 py-3 text-muted">{{ $ship->provinsi ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold
                                        {{ match ($ship->status_akhir) {
                                            'Completed' => 'bg-green-100 text-green-700 dark:bg-green-950/80 dark:text-green-300',
                                            'On Delivery' => 'bg-blue-100 text-blue-700 dark:bg-blue-950/80 dark:text-blue-300',
                                            'Undelivered' => 'bg-red-100 text-red-700 dark:bg-red-950/80 dark:text-red-300',
                                            'Hold' => 'bg-orange-100 text-orange-700 dark:bg-orange-950/80 dark:text-orange-300',
                                            default => 'bg-surface-subtle text-muted dark:bg-slate-950 dark:text-slate-300',
                                        } }}">
                                        {{ $ship->status_akhir }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-muted">{{ $ship->stagging ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex px-2 py-0.5 rounded-md text-xs font-semibold
                                        {{ ($ship->days_open ?? 0) >= 30 ? 'bg-red-100 text-red-700 dark:bg-red-950/80 dark:text-red-300' : (($ship->days_open ?? 0) >= 14 ? 'bg-amber-100 text-amber-700 dark:bg-amber-950/80 dark:text-amber-300' : 'bg-surface-subtle text-muted dark:bg-slate-950 dark:text-slate-300') }}">
                                        {{ $ship->days_open !== null ? $ship->days_open . ' hari' : '—' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold
                                        {{ $ship->sla_result === 'Out SLA' ? 'bg-red-50 text-red-600 border border-red-200 dark:bg-red-950/80 dark:text-red-300 dark:border-red-800' : 'bg-emerald-50 text-emerald-600 border border-emerald-200 dark:bg-emerald-950/80 dark:text-emerald-300 dark:border-emerald-800' }}">
                                        {{ $ship->sla_result ?? '—' }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="mt-4 text-sm text-muted">Tidak ada pengiriman yang perlu perhatian pada filter ini.</p>
        @endif
    </div>

    {{-- Recent table --}}
    <div id="pengiriman-terbaru" class="bg-surface rounded-xl shadow-sm border border-border overflow-hidden stagger-ready">
        <div class="px-6 py-5 border-b border-border">
            <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold text-ink">Pengiriman Terbaru</h2>
                    <p class="text-sm text-muted">8 resi terakhir</p>
                </div>
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-muted">
                    <span class="inline-flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-green-500 dark:bg-green-400"></span>Completed
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-blue-500 dark:bg-blue-400"></span>On Delivery
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-red-500 dark:bg-red-400"></span>Undelivered
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-orange-500 dark:bg-orange-400"></span>Hold
                    </span>
                    <span class="italic text-muted" title="Hold = pengiriman ditahan / sedang menunggu tindak lanjut (follow-up), mis. menunggu info alamat atau persetujuan">
                        Hold = ditahan / menunggu follow-up
                    </span>
                </div>
            </div>
        </div>
        <div class="overflow-x-auto dark:bg-surface">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="bg-surface-subtle text-xs uppercase tracking-wider text-muted border-b border-border">
                        <th class="px-6 py-3 font-semibold">No Resi</th>
                        <th class="px-6 py-3 font-semibold">Sekolah</th>
                        <th class="px-6 py-3 font-semibold">Provinsi</th>
                        <th class="px-6 py-3 font-semibold">Stagging</th>
                        <th class="px-6 py-3 font-semibold">Status Akhir</th>
                        <th class="px-6 py-3 font-semibold">SLA</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border/70">
                    @forelse ($recentShipments as $ship)
                        <tr class="hover:bg-surface-subtle transition">
                            <td class="px-6 py-3.5 font-mono text-ink">{{ $ship->no_resi }}</td>
                            <td class="px-6 py-3.5 text-ink font-medium max-w-[200px] truncate">{{ $ship->nama_sekolah }}</td>
                            <td class="px-6 py-3.5 text-muted">{{ $ship->provinsi ?? '—' }}</td>
                            <td class="px-6 py-3.5 text-muted">{{ $ship->stagging ?? '—' }}</td>
                            <td class="px-6 py-3.5">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold
                                    {{ match ($ship->status_akhir) {
                                        'Completed' => 'bg-green-100 text-green-700 dark:bg-green-950/80 dark:text-green-300',
                                        'On Delivery' => 'bg-blue-100 text-blue-700 dark:bg-blue-950/80 dark:text-blue-300',
                                        'Undelivered' => 'bg-red-100 text-red-700 dark:bg-red-950/80 dark:text-red-300',
                                        'Hold' => 'bg-orange-100 text-orange-700 dark:bg-orange-950/80 dark:text-orange-300',
                                        default => 'bg-surface-subtle text-muted dark:bg-slate-950 dark:text-slate-300',
                                    } }}">
                                    <span class="w-1.5 h-1.5 rounded-full
                                        {{ match ($ship->status_akhir) {
                                            'Completed' => 'bg-green-500 dark:bg-green-400',
                                            'On Delivery' => 'bg-blue-500 dark:bg-blue-400',
                                            'Undelivered' => 'bg-red-500 dark:bg-red-400',
                                            'Hold' => 'bg-orange-500 dark:bg-orange-400',
                                            default => 'bg-slate-400 dark:bg-slate-300',
                                        } }}"></span>
                                    {{ $ship->status_akhir }}
                                </span>
                            </td>
                            <td class="px-6 py-3.5">
                                <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold
                                    {{ $ship->sla_result === 'Out SLA' ? 'bg-red-50 text-red-600 border border-red-200 dark:bg-red-950/80 dark:text-red-300 dark:border-red-800' : 'bg-emerald-50 text-emerald-600 border border-emerald-200 dark:bg-emerald-950/80 dark:text-emerald-300 dark:border-emerald-800' }}">
                                    {{ $ship->sla_result ?? '—' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-10 text-center text-muted">Belum ada data pengiriman.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

{{-- Modal Detail --}}
    <style>[x-cloak]{display:none!important}</style>

    <div x-show="modalOpen"
         x-cloak
         x-transition.opacity
         @keydown.escape.window="modalOpen = false"
         class="fixed inset-0 z-[60] flex items-center justify-center p-4 sm:p-6">
        <div class="absolute inset-0 bg-black/60 dark:bg-black/80" @click="modalOpen = false"></div>
        <div class="relative w-full max-w-4xl max-h-[88vh] flex flex-col bg-surface rounded-2xl shadow-2xl overflow-hidden">
            <div class="flex items-start justify-between gap-4 px-6 py-4 border-b border-border bg-surface-subtle shrink-0">
                <div class="min-w-0">
                    <h3 class="text-lg font-bold text-ink truncate" x-text="modalTitle"></h3>
                    <p class="text-sm text-muted" x-text="modalSubtitle"></p>
                </div>
                <button @click="modalOpen = false" class="p-2 rounded-lg text-muted hover:bg-border hover:text-ink dark:hover:bg-slate-800 dark:hover:text-ink transition shrink-0" aria-label="Tutup">
                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="flex-1 min-h-0 overflow-auto dark:bg-surface">
                <table class="w-full min-w-[720px] text-left text-sm">
                    <thead class="sticky top-0 z-10 bg-surface-subtle">
                        <tr class="text-xs uppercase tracking-wider text-muted border-b border-border">
                            <th class="px-6 py-3 font-semibold">No Resi</th>
                            <th class="px-6 py-3 font-semibold">Sekolah</th>
                            <th class="px-6 py-3 font-semibold">Provinsi</th>
                            <th class="px-6 py-3 font-semibold">Kota/Kab</th>
                            <th class="px-6 py-3 font-semibold">Status</th>
                            <th class="px-6 py-3 font-semibold">SLA</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/70">
                        <template x-for="row in modalRows" :key="row.id">
                            <tr class="hover:bg-surface-subtle">
                                <td class="px-6 py-3 font-mono text-ink whitespace-nowrap">
                                    <span x-text="row.no_resi || '—'"></span>
                                    <span x-show="row.resi_catatan"
                                          class="mt-1 inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide"
                                          :class="row.resi_catatan === 'DUPLIKAT'
                                              ? 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300'
                                              : 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300'"
                                          x-text="row.resi_catatan === 'DUPLIKAT' ? 'Duplikat' : 'Resi bermasalah'"></span>
                                </td>
                                <td class="px-6 py-3 text-ink font-medium max-w-[260px] truncate" x-text="row.nama_sekolah"></td>
                                <td class="px-6 py-3 text-muted" x-text="row.provinsi ?? '—'"></td>
                                <td class="px-6 py-3 text-muted" x-text="row.kota_kabupaten ?? '—'"></td>
                                <td class="px-6 py-3">
                                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold"
                                          :class="statusClass(row.status_akhir)"
                                          x-text="row.status_akhir"></span>
                                </td>
                                <td class="px-6 py-3">
                                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold"
                                          :class="row.sla_result === 'Out SLA' ? 'bg-red-50 text-red-600 border border-red-200 dark:bg-red-950/80 dark:text-red-300 dark:border-red-800' : 'bg-emerald-50 text-emerald-600 border border-emerald-200 dark:bg-emerald-950/80 dark:text-emerald-300 dark:border-emerald-800'"
                                          x-text="row.sla_result ?? '—'"></span>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="modalLoading">
                            <td colspan="6" class="px-6 py-10 text-center text-muted">Memuat data...</td>
                        </tr>
                        <tr x-show="!modalLoading && modalRows.length === 0">
                            <td colspan="6" class="px-6 py-10 text-center text-muted">Tidak ada data pada filter ini.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="flex items-center justify-between gap-4 px-6 py-3 border-t border-border bg-surface-subtle shrink-0">
                <p class="text-sm text-muted">
                    Menampilkan <span class="font-semibold text-ink" x-text="modalRows.length"></span> resi
                    <span x-show="modalRowsFound > modalRows.length" class="text-muted" x-text="'dari ' + modalRowsFound"></span>
                </p>
                <button @click="modalOpen = false"
                    class="px-4 py-2 rounded-lg text-sm font-semibold text-ink bg-surface border border-border hover:bg-surface-subtle transition">
                    Tutup
                </button>
            </div>
        </div>
    </div>

@push('scripts')
<style>[x-cloak]{display:none!important}</style>
<style>
.intro-splash { z-index: 60; }
.intro-logo { animation: introPulse 1.4s ease-in-out infinite; }
.intro-track { width: 14rem; }
@keyframes introPulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}
.intro-progress-bar { width: 0%; animation: introFill 1.15s ease-out forwards; }
@keyframes introFill { to { width: 100%; } }

.intro-active .stagger-ready > * {
    opacity: 0;
    transform: translateY(14px);
    animation: introRise .55s cubic-bezier(.22, 1, .36, 1) forwards;
}
.intro-active .stagger-ready > *:nth-child(1) { animation-delay: .05s; }
.intro-active .stagger-ready > *:nth-child(2) { animation-delay: .12s; }
.intro-active .stagger-ready > *:nth-child(3) { animation-delay: .19s; }
.intro-active .stagger-ready > *:nth-child(4) { animation-delay: .26s; }
.intro-active .stagger-ready > *:nth-child(5) { animation-delay: .33s; }
.intro-active .stagger-ready > *:nth-child(6) { animation-delay: .40s; }
.intro-active .stagger-ready > *:nth-child(7) { animation-delay: .47s; }
.intro-active .stagger-ready > *:nth-child(8) { animation-delay: .54s; }
.intro-active .stagger-ready > *:nth-child(n+9) { animation-delay: .60s; }
@keyframes introRise { to { opacity: 1; transform: none; } }
@media (prefers-reduced-motion: reduce) {
    .intro-active .stagger-ready > * { animation: none; opacity: 1; transform: none; }
}
</style>
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('introController', () => ({
        splash: false,
        init() {
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || sessionStorage.getItem('anl_intro_shown')) {
                return;
            }
            sessionStorage.setItem('anl_intro_shown', '1');
            this.splash = true;
            setTimeout(() => {
                this.splash = false;
                document.documentElement.classList.add('intro-active');
            }, 3000);
            setTimeout(() => document.documentElement.classList.remove('intro-active'), 3000);
        },
        skip() {
            this.splash = false;
            document.documentElement.classList.remove('intro-active');
        },
    }));
});
</script>
@php($modalFilters = array_filter(['vendor' => $vendor, 'range' => $range ?: null]))
<script>
const modalFilters = @json($modalFilters);
document.addEventListener('alpine:init', () => {
    Alpine.data('modalController', () => ({
        modalOpen: false,
        modalTitle: '',
        modalSubtitle: '',
        modalRows: [],
        modalRowsFound: 0,
        modalLoading: false,
        statusClass(status) {
            return {
                'Completed': 'bg-green-100 text-green-700 dark:bg-green-950/80 dark:text-green-300',
                'On Delivery': 'bg-blue-100 text-blue-700 dark:bg-blue-950/80 dark:text-blue-300',
                'Undelivered': 'bg-red-100 text-red-700 dark:bg-red-950/80 dark:text-red-300',
                'Hold': 'bg-orange-100 text-orange-700 dark:bg-orange-950/80 dark:text-orange-300',
            }[status] || 'bg-surface-subtle text-muted dark:bg-slate-950 dark:text-slate-300';
        },
        activeFilterLabel() {
            return modalFilters.vendor ? ' · vendor ' + modalFilters.vendor : '';
        },
        modalUrl(path, params) {
            const query = params.toString();
            return query ? path + '?' + query : path;
        },
        openStaging(stage, total) {
            const params = new URLSearchParams(modalFilters);
            this.loadModal(
                'Stagging: ' + stage,
                'Resi yang sedang berada di tahap ini (maks. 50 tampil)' + this.activeFilterLabel(),
                this.modalUrl('/api/shipments/staging/' + encodeURIComponent(stage), params),
                Number(total)
            );
        },
        openBottleneck(provinsi, total) {
            const params = new URLSearchParams(modalFilters);
            params.set('provinsi', provinsi);
            this.loadModal(
                'Over SLA — ' + provinsi,
                'Resi yang melewati SLA di provinsi ini (maks. 50 tampil)' + this.activeFilterLabel(),
                this.modalUrl('/api/shipments/bottleneck', params),
                Number(total)
            );
        },
        loadModal(title, subtitle, url, foundTotal) {
            this.modalTitle = title;
            this.modalSubtitle = subtitle;
            this.modalRows = [];
            this.modalRowsFound = foundTotal || 0;
            this.modalLoading = true;
            this.modalOpen = true;

            fetch(url)
                .then(r => r.json())
                .then(rows => { this.modalRows = rows; this.modalLoading = false; })
                .catch(() => { this.modalRows = []; this.modalLoading = false; });
        },
    }));
});

(() => {
    const chartRegistry = new Map();
    const isDarkTheme = () => document.documentElement.classList.contains('dark');

    const lightPalette = {
        ink: '#0f172a',
        muted: '#64748b',
        subtle: '#475569',
        grid: 'rgba(15,23,42,0.06)',
        border: '#e2e8f0',
        surface: '#ffffff',
        pointBackground: '#ffffff',
        legend: '#334155',
        tooltipBackground: '#0f172a',
        tooltipTitle: '#f8fafc',
        tooltipBody: '#e2e8f0',
        trend: {
            send: '#0891b2',
            sendHover: '#06b6d4',
            sendBottom: 'rgba(8,145,178,0.12)',
            sendHoverBottom: 'rgba(6,182,212,0.25)',
            completed: '#2563eb',
            out: '#ef4444',
            compliance: '#10b981',
        },
        province: {
            start: '#0891b2',
            end: 'rgba(8,145,178,0.30)',
            hoverStart: '#06b6d4',
            hoverEnd: 'rgba(6,182,212,0.40)',
        },
        categorical: ['#075985', '#0891b2', '#06b6d4', '#22d3ee', '#67e8f9', '#94a3b8'],
        status: {
            Completed: '#22c55e',
            'On Delivery': '#3b82f6',
            Hold: '#f59e0b',
            Undelivered: '#ef4444',
        },
        bast: ['#10b981', '#f59e0b', '#94a3b8', '#3b82f6', '#a855f7', '#64748b'],
        finance: ['#0891b2', '#cbd5e1'],
    };

    const darkPalette = {
        ink: '#f8fafc',
        muted: '#94a3b8',
        subtle: '#cbd5e1',
        grid: 'rgba(148,163,184,0.16)',
        border: '#334155',
        surface: '#0f172a',
        pointBackground: '#f8fafc',
        legend: '#cbd5e1',
        tooltipBackground: '#020617',
        tooltipTitle: '#f8fafc',
        tooltipBody: '#e2e8f0',
        trend: {
            send: '#22d3ee',
            sendHover: '#67e8f9',
            sendBottom: 'rgba(34,211,238,0.10)',
            sendHoverBottom: 'rgba(34,211,238,0.25)',
            completed: '#60a5fa',
            out: '#f87171',
            compliance: '#34d399',
        },
        province: {
            start: '#22d3ee',
            end: 'rgba(34,211,238,0.22)',
            hoverStart: '#67e8f9',
            hoverEnd: 'rgba(34,211,238,0.36)',
        },
        categorical: ['#22d3ee', '#60a5fa', '#34d399', '#fbbf24', '#f87171', '#c084fc', '#cbd5e1'],
        status: {
            Completed: '#34d399',
            'On Delivery': '#60a5fa',
            Hold: '#fbbf24',
            Undelivered: '#f87171',
        },
        bast: ['#34d399', '#fbbf24', '#cbd5e1', '#60a5fa', '#c084fc', '#94a3b8'],
        finance: ['#22d3ee', '#cbd5e1'],
    };

    const paletteFor = () => isDarkTheme() ? darkPalette : lightPalette;

    const hexToRgba = (hex, alpha) => {
        const value = hex.replace('#', '');
        const r = parseInt(value.slice(0, 2), 16);
        const g = parseInt(value.slice(2, 4), 16);
        const b = parseInt(value.slice(4, 6), 16);
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    };

    const verticalGradient = (ctx, area, stops) => {
        const gradient = ctx.createLinearGradient(0, area.top, 0, area.bottom);
        stops.forEach(([position, color]) => gradient.addColorStop(position, color));
        return gradient;
    };

    const barGradient = (topColor, bottomColor) => ({ chart }) => {
        const { ctx, chartArea } = chart;
        if (!chartArea || chartArea.bottom <= chartArea.top) return topColor;
        return verticalGradient(ctx, chartArea, [[0, topColor], [1, bottomColor]]);
    };

    const areaGradient = (hex, alphaTop, alphaBottom) => ({ chart }) => {
        const { ctx, chartArea } = chart;
        if (!chartArea || chartArea.bottom <= chartArea.top) return hexToRgba(hex, alphaTop);
        return verticalGradient(ctx, chartArea, [
            [0, hexToRgba(hex, alphaTop)],
            [1, hexToRgba(hex, alphaBottom)],
        ]);
    };

    const hbarGradient = (startColor, endColor) => ({ chart }) => {
        const { ctx, chartArea } = chart;
        if (!chartArea || chartArea.right <= chartArea.left) return startColor;
        const gradient = ctx.createLinearGradient(chartArea.left, 0, chartArea.right, 0);
        gradient.addColorStop(0, startColor);
        gradient.addColorStop(1, endColor);
        return gradient;
    };

    const doughnutGradient = (context, hex) => {
        const { chart } = context;
        const { ctx, chartArea } = chart;
        if (!chartArea || !chartArea.width || !chartArea.height) return hex;
        const { left, top, right, bottom } = chartArea;
        const centerX = (left + right) / 2;
        const centerY = (top + bottom) / 2;
        const radius = Math.min(right - left, bottom - top) / 2;
        const gradient = ctx.createRadialGradient(centerX, centerY, radius * 0.15, centerX, centerY, radius);
        gradient.addColorStop(0, hexToRgba(hex, 0.45));
        gradient.addColorStop(1, hex);
        return gradient;
    };

    const modernAnimation = { duration: 900, easing: 'easeOutQuart' };

    const modernTooltip = () => {
        const palette = paletteFor();
        return {
            backgroundColor: palette.tooltipBackground,
            titleColor: palette.tooltipTitle,
            bodyColor: palette.tooltipBody,
            borderColor: palette.border,
            borderWidth: 1,
            padding: 12,
            cornerRadius: 10,
            boxPadding: 6,
            usePointStyle: true,
            titleFont: { weight: '700', size: 13 },
            bodyFont: { size: 12 },
        };
    };

    const pctTooltip = (total) => ({
        ...modernTooltip(),
        callbacks: {
            label(context) {
                const value = Number(context.parsed ?? 0);
                const pct = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                return ` ${context.label}: ${value.toLocaleString('id-ID')} (${pct}%)`;
            },
        },
    });

    const modernLegend = () => {
        const palette = paletteFor();
        return {
            position: 'bottom',
            labels: {
                usePointStyle: true,
                pointStyle: 'circle',
                boxWidth: 7,
                boxHeight: 7,
                padding: 18,
                color: palette.legend,
                font: { size: 12, weight: '500' },
            },
        };
    };

    const trendLabels = @json($chartData->map(fn ($d) => \Illuminate\Support\Carbon::parse($d->tanggal)->format('d M'))->values());
    const trendKirim = @json($chartData->pluck('volume_kirim')->map(fn ($v) => (int) $v)->values());
    const trendSelesai = @json($chartData->pluck('volume_selesai')->map(fn ($v) => (int) $v)->values());
    const trendOutSla = @json($chartData->map(fn ($d) => (int) ($outSlaDaily[$d->tanggal]->volume_out ?? 0))->values());
    const trendCompliance = @json($chartData->map(function ($d) use ($slaComplianceDaily) {
        $row = $slaComplianceDaily[$d->tanggal] ?? null;
        if (! $row || (int) $row->total <= 0) return null;
        return (int) round(((int) $row->meet / (int) $row->total) * 100);
    })->values());

    const provinsiLabels = @json($topProvinces->keys()->values());
    const provinsiData = @json($topProvinces->values());
    const vendorLabels = @json($topVendors->keys()->values());
    const vendorData = @json($topVendors->values());
    const vendorTotal = vendorData.reduce((total, value) => total + Number(value || 0), 0);
    const statusLabels = @json($statusBreakdown->keys()->values());
    const statusData = @json($statusBreakdown->values());
    const statusTotal = statusData.reduce((total, value) => total + Number(value || 0), 0);
    const slaData = @json([$withinSla, $overSla]);
    const slaTotal = slaData.reduce((total, value) => total + Number(value || 0), 0);
    const financeLabels = @json($bastFinanceStats->keys()->values());
    const financeData = @json($bastFinanceStats->values());
    const financeTotal = financeData.reduce((total, value) => total + Number(value || 0), 0);

    const trendConfig = () => {
        const palette = paletteFor();
        const colors = palette.trend;
        return {
            type: 'bar',
            data: {
                labels: trendLabels,
                datasets: [
                    {
                        type: 'bar',
                        label: 'Volume Kirim',
                        data: trendKirim,
                        backgroundColor: barGradient(colors.send, colors.sendBottom),
                        hoverBackgroundColor: barGradient(colors.sendHover, colors.sendHoverBottom),
                        borderRadius: { topLeft: 6, topRight: 6 },
                        barPercentage: 0.6,
                        categoryPercentage: 0.8,
                    },
                    {
                        type: 'line',
                        label: 'Volume Selesai',
                        data: trendSelesai,
                        borderColor: colors.completed,
                        backgroundColor: colors.completed,
                        borderWidth: 2.5,
                        tension: 0.4,
                        spanGaps: true,
                        fill: { target: 'origin', above: areaGradient(colors.completed, 0.16, 0.03) },
                        pointBackgroundColor: palette.pointBackground,
                        pointBorderColor: colors.completed,
                        pointBorderWidth: 2,
                        pointRadius: 3,
                        pointHitRadius: 10,
                        pointHoverRadius: 5,
                        pointHoverBackgroundColor: colors.completed,
                        pointHoverBorderColor: palette.pointBackground,
                    },
                    {
                        type: 'line',
                        label: 'Out SLA',
                        data: trendOutSla,
                        borderColor: colors.out,
                        backgroundColor: colors.out,
                        borderWidth: 2,
                        borderDash: [6, 4],
                        tension: 0.4,
                        spanGaps: true,
                        fill: { target: 'origin', above: areaGradient(colors.out, 0.10, 0.02) },
                        pointBackgroundColor: palette.pointBackground,
                        pointBorderColor: colors.out,
                        pointBorderWidth: 2,
                        pointRadius: 3,
                        pointHitRadius: 10,
                        pointHoverRadius: 5,
                        pointHoverBackgroundColor: colors.out,
                        pointHoverBorderColor: palette.pointBackground,
                    },
                    {
                        type: 'line',
                        yAxisID: 'y1',
                        label: 'SLA Compliance %',
                        data: trendCompliance,
                        borderColor: colors.compliance,
                        backgroundColor: colors.compliance,
                        borderWidth: 2,
                        tension: 0.4,
                        spanGaps: true,
                        fill: { target: 'origin', above: areaGradient(colors.compliance, 0.12, 0.02) },
                        pointBackgroundColor: palette.pointBackground,
                        pointBorderColor: colors.compliance,
                        pointBorderWidth: 2,
                        pointRadius: 3,
                        pointHitRadius: 10,
                        pointHoverRadius: 5,
                        pointHoverBackgroundColor: colors.compliance,
                        pointHoverBorderColor: palette.pointBackground,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: modernAnimation,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: modernLegend(),
                    tooltip: {
                        ...modernTooltip(),
                        callbacks: {
                            label(context) {
                                const raw = context.parsed?.y ?? 0;
                                const value = Number.isInteger(raw) ? raw : Math.round(raw);
                                if (context.dataset.yAxisID === 'y1') return ` ${context.dataset.label}: ${value}%`;
                                return ` ${context.dataset.label}: ${value.toLocaleString('id-ID')}`;
                            },
                        },
                    },
                },
                scales: {
                    x: {
                        grid: { display: false, color: palette.grid },
                        border: { display: false },
                        ticks: {
                            color: palette.muted,
                            font: { size: 11 },
                            maxRotation: 0,
                            autoSkip: true,
                            maxTicksLimit: 12,
                        },
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: palette.grid },
                        border: { display: false },
                        ticks: {
                            color: palette.muted,
                            font: { size: 11 },
                            precision: 0,
                            callback: (value) => Number(value).toLocaleString('id-ID'),
                        },
                    },
                    y1: {
                        position: 'right',
                        min: 0,
                        max: 100,
                        grid: { color: palette.grid, drawOnChartArea: false },
                        border: { display: false },
                        ticks: {
                            color: palette.subtle,
                            font: { size: 11 },
                            precision: 0,
                            callback: (value) => value + '%',
                        },
                    },
                },
            },
        };
    };

    const provinsiConfig = () => {
        const palette = paletteFor();
        return {
            type: 'bar',
            data: {
                labels: provinsiLabels,
                datasets: [{
                    label: 'Jumlah Resi',
                    data: provinsiData,
                    backgroundColor: hbarGradient(palette.province.start, palette.province.end),
                    hoverBackgroundColor: hbarGradient(palette.province.hoverStart, palette.province.hoverEnd),
                    borderColor: palette.surface,
                    borderSkipped: false,
                    borderRadius: { topRight: 6, bottomRight: 6 },
                    barPercentage: 0.6,
                    categoryPercentage: 0.8,
                }],
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                animation: modernAnimation,
                plugins: {
                    legend: { display: false },
                    tooltip: modernTooltip(),
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: { color: palette.grid },
                        border: { display: false },
                        ticks: {
                            color: palette.muted,
                            font: { size: 11 },
                            precision: 0,
                            callback: (value) => Number(value).toLocaleString('id-ID'),
                        },
                    },
                    y: {
                        grid: { display: false, color: palette.grid },
                        border: { display: false },
                        ticks: { color: palette.subtle, font: { size: 12, weight: '600' } },
                    },
                },
            },
        };
    };

    const doughnutConfig = ({ labels, data, total, cutout, colorAt, borderRadius = 5, spacing = 2 }) => {
        const palette = paletteFor();
        const colorFor = (index) => colorAt(Number.isInteger(index) ? index : 0, palette);
        return {
            type: 'doughnut',
            data: {
                labels,
                datasets: [{
                    data,
                    backgroundColor: (context) => doughnutGradient(context, colorFor(context.dataIndex)),
                    hoverBackgroundColor: (context) => doughnutGradient(context, colorFor(context.dataIndex)),
                    borderColor: palette.surface,
                    hoverBorderColor: palette.border,
                    borderWidth: 3,
                    borderRadius,
                    spacing,
                    hoverOffset: 8,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout,
                animation: modernAnimation,
                plugins: {
                    legend: modernLegend(),
                    tooltip: pctTooltip(total),
                },
            },
        };
    };

    const vendorConfig = () => doughnutConfig({
        labels: vendorLabels,
        data: vendorData,
        total: vendorTotal,
        cutout: '58%',
        colorAt: (index, palette) => palette.categorical[index % palette.categorical.length],
    });

    const statusConfig = () => doughnutConfig({
        labels: statusLabels,
        data: statusData,
        total: statusTotal,
        cutout: '60%',
        colorAt: (index, palette) => palette.status[statusLabels[index]] || palette.muted,
    });

    const slaConfig = () => doughnutConfig({
        labels: ['Meet SLA', 'Out SLA'],
        data: slaData,
        total: slaTotal,
        cutout: '76%',
        colorAt: (index, palette) => [palette.trend.compliance, palette.trend.out][index],
        borderRadius: 6,
        spacing: 3,
    });

    const financeConfig = () => doughnutConfig({
        labels: financeLabels,
        data: financeData,
        total: financeTotal,
        cutout: '60%',
        colorAt: (index, palette) => palette.finance[index % palette.finance.length],
    });

    const destroyCharts = () => {
        chartRegistry.forEach((chart) => chart.destroy());
        chartRegistry.clear();
    };

    const renderChart = (id, factory) => {
        const canvas = document.getElementById(id);
        if (!canvas || typeof Chart === 'undefined') return;
        chartRegistry.set(id, new Chart(canvas.getContext('2d'), factory()));
    };

    const renderCharts = () => {
        destroyCharts();
        if (typeof Chart === 'undefined') return;
        if (trendLabels.length) renderChart('trendChart', trendConfig);
        renderChart('provinsiChart', provinsiConfig);
        renderChart('vendorChart', vendorConfig);
        renderChart('statusChart', statusConfig);
        renderChart('slaChart', slaConfig);
    renderChart('financeChart', financeConfig);

    };

    let chartsInitialized = false;
    const initializeCharts = () => {
        if (chartsInitialized) return;
        chartsInitialized = true;
        window.addEventListener('anl-theme-changed', renderCharts);
        renderCharts();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeCharts, { once: true });
    } else {
        initializeCharts();
    }
})();
</script>
@endpush

</div>

</x-app-layout>