# Cleanup pass — 16 Oktober 2026

Fase 2 dijalankan setelah persetujuan untuk A01–A14, D01–D03, dan B01–B08 pada audit. Perubahan yang sebelumnya sudah ada di working tree dipertahankan. Semua item HOLD dikecualikan.

## Penghapusan yang disetujui

| ID | Perubahan | Lokasi untuk review |
| --- | --- | --- |
| A01 | Menghapus file komponen `Separator` tanpa pemakai. Daftar komponen di README ikut disesuaikan. | `resources/js/components/ui/separator.tsx` dihapus; `README.md` |
| A02 | Menghapus `OpenTripForm` tanpa pemakai. | `resources/js/features/trips/components/OpenTripForm.tsx` dihapus |
| A03 | Menghapus tampilan `PeriodOverview`, helper privat, dan import tampilannya. `PeriodProps` beserta tipe pendukung tetap berada pada path yang sama. | [PeriodOverview.tsx](../resources/js/features/reports/components/PeriodOverview.tsx) |
| A04 | Menghapus `CardHeader`, `CardTitle`, `CardDescription`, `CardAction`, dan `CardFooter`. `Card` dan `CardContent` tetap ada. | [card.tsx](../resources/js/components/ui/card.tsx) |
| A05 | Menghapus interface frontend `StockLot` dan `StockMovement`. Model inventory backend tetap ada. | [sales.ts](../resources/js/types/sales.ts) |
| A06 | Menghapus import `weight` yang tidak dibaca. | [LegacySaleForm.tsx](../resources/js/features/sales/components/LegacySaleForm.tsx) |
| A07 | Menghapus cabang referensi trips yang tidak diizinkan oleh parameter route, beserta import model khusus cabang itu. | [CashController.php](../app/Http/Controllers/CashController.php) |
| A08 | Menghapus pesan form dan penyusunan opsi `cash-accounts` yang tidak terjangkau karena policy menolak create/update. Pembuatan Kas Usaha melalui action terpisah tetap ada. | [MasterDataForm.tsx](../resources/js/features/master-data/components/MasterDataForm.tsx), [MasterDataController.php](../app/Http/Controllers/MasterData/MasterDataController.php) |
| A09 | Menghapus method `InventoryController::index()` yang tidak memiliki route. Method inventory lain tetap ada. | [InventoryController.php](../app/Http/Controllers/InventoryController.php) |
| A10 | Menghapus method `TripController::saleContext()` tanpa pemanggil/route. | [TripController.php](../app/Http/Controllers/TripController.php) |
| A11 | Menghapus commented import dan contoh konfigurasi nonaktif; komentar penjelasan operasional dipertahankan. | `app/Models/User.php`; `config/auth.php`, `database.php`, `mail.php`, `cache.php` |
| A12 | Menjadikan empat array navigasi sebagai konstanta lokal; isi dan pemakaiannya tetap. | [navigation.ts](../resources/js/lib/navigation.ts) |
| A13 | Menghapus export `badgeVariants` dan `WorkspaceMenuLinks`; implementasi lokal tetap digunakan. | [badge.tsx](../resources/js/components/ui/badge.tsx), [WorkspaceMenu.tsx](../resources/js/components/common/WorkspaceMenu.tsx) |
| A14 | Menghapus modifier export pada `FieldValue`, `WeighingItem`, `ReportRow`, `SaleAllocation`, `PayrollHistoryFilters`, dan `VoucherDocumentProps`. Seluruh deklarasi tetap ada untuk pemakaian lokal. | `resources/js/types/{master-data,operations,reports,sales,payroll-history}.ts`; `features/finance/components/VoucherDocument.tsx` |

## Ekstraksi logic bersama

| ID | Perubahan | Batas perilaku yang dipertahankan |
| --- | --- | --- |
| D01 | Lifecycle request preview dipusatkan di [use-preview-request.ts](../resources/js/hooks/use-preview-request.ts). Hook penjualan dan timbang tetap menjadi pembungkus domain masing-masing. | Debounce 250 ms, abort, penolakan respons lama, refresh, validasi awal, initial loading, pesan error, dan definisi `ready` masing-masing. |
| D02 | Aturan file bukti dipusatkan di [ProofUploadRules.php](../app/Support/ProofUploadRules.php). | Jenis/ukuran file sama. Otorisasi awal dan pemeriksaan ulang saat menyimpan tetap dilakukan oleh controller masing-masing. |
| D03 | Lifecycle navigasi filter dipusatkan di [use-filter-visit.ts](../resources/js/hooks/use-filter-visit.ts), dipakai oleh filter payroll, penjualan, timbang, dan hook riwayat payroll hasil B03. | Payload, default filter, mode `periodOnly`, `preserveState`, `preserveScroll`, dan perubahan loading tetap mengikuti pemanggil. |

## Pemecahan file

| ID | Perubahan | Lokasi baru/utama |
| --- | --- | --- |
| B01 | State, preview, dan submit form dipisahkan dari tampilan. Form timbang memisahkan berat, anggota muat, dan harga; form penjualan memisahkan tampilan pabrik/sortir, field bersama, dan pemilih mobil. | `features/weighings/hooks/use-weighing-form.ts`, `Weighing{Weights,Workers,Price}Section.tsx`; `features/sales/hooks/use-sale-form.ts`, `{Factory,Sort}SaleEntryForm.tsx`, `SaleFormField.tsx`, `SaleVehiclePicker.tsx` |
| B02 | State lima mutasi penjualan dipisahkan dari detail dokumen, aksi, dan bukti. | `features/sales/hooks/use-sale-actions.ts`, `components/Sale{Details,Actions,Proofs}.tsx`, `types/sale-show.ts` |
| B03 | Riwayat payroll dipisahkan menjadi hook navigasi/filter, formatter tanggal, identitas pekerja, pekerjaan lunas, voucher pembayaran, dan form filter. | `features/finance/hooks/use-payroll-history.ts`, `lib/payroll-history.ts`, `components/payroll-history/` |
| B04 | Verifikasi/unpack arsip dan retensi dipisahkan dari orkestrasi backup/restore. | [BackupService.php](../app/Services/BackupService.php), [BackupArchive.php](../app/Services/BackupArchive.php), [BackupRetention.php](../app/Services/BackupRetention.php) |
| B05 | Query entitlement, riwayat payroll, dan daftar penerimaan dipisahkan dari controller. | `app/Queries/{FinanceEntitlements,PayrollHistoryQuery,SaleReceiptIndex}.php` |
| B06 | Stylesheet dipecah berdasarkan tanggung jawab. `app.css` menjadi daftar import berurutan. Lokasi token pada README disesuaikan. | `resources/css/{foundation,workspace,dashboard,factory-sales,payroll,reports}.css` |
| B07 | Method test keuangan dan penjualan/inventory dikelompokkan dalam trait berdasarkan perilaku. Fixture dan class test asal tetap digunakan. | `tests/Concerns/{FinanceTest,SalesAndInventoryTest}/` |
| B08 | Method test penjualan langsung/summary dan harga/timbang dikelompokkan dengan cara yang sama. | `tests/Concerns/{DirectSalesAndSummaryTest,PricesAndWeighingsTest}/` |

Semua path frontend pada tabel relatif terhadap `resources/js/`. Pemecahan test mempertahankan nama class dan method sehingga filter PHPUnit yang sudah digunakan tetap berlaku.

| File utama | Baris sebelum | Baris sesudah |
| --- | ---: | ---: |
| `WeighingForm.tsx` | 524 | 210 |
| `SaleForm.tsx` | 482 | 22 |
| `pages/Sales/Show.tsx` | 513 | 42 |
| `pages/Finance/PayrollHistory.tsx` | 489 | 165 |
| `BackupService.php` | 430 | 288 |
| `FinanceController.php` | 352 | 235 |
| `app.css` | 753 | 7 |
| `FinanceTest.php` | 787 | 134 |
| `SalesAndInventoryTest.php` | 728 | 135 |
| `DirectSalesAndSummaryTest.php` | 658 | 94 |
| `PricesAndWeighingsTest.php` | 522 | 109 |

Baris yang dipindahkan tetap berada di modul baru; pengurangan ukuran file utama bukan klaim bahwa seluruh baris tersebut dihapus.

## Validasi

- Seluruh suite PHPUnit: **354 test, 8.637 assertion, lulus**.
- Discovery PHPUnit sebelum/sesudah: **354 nama test identik**.
- AST dari **116 method test** yang dipindahkan identik, termasuk seluruh assertion dan literalnya.
- Signature **313 method publik backend** tetap identik. Dua method orphan A09/A10 merupakan penghapusan yang disetujui.
- Build produksi dan TypeScript dengan pemeriksaan unused locals/parameters lulus.
- Perbandingan **206 skenario render dan submit** terhadap salinan sebelum cleanup lulus: form timbang, penjualan pabrik/sortir, detail penjualan, riwayat payroll, serta filter. Role, status, koreksi, penugasan mobil, loading, dan error divariasikan.
- Perbandingan **49 pemeriksaan state/request preview** lulus, mencakup debounce, abort, respons lama, refresh, validasi, error backend/network/cancel, serta preview null.
- Isi CSS setelah import diperluas memiliki urutan yang sama dengan stylesheet sebelum pemecahan.
- Pint, Prettier, dan pemeriksaan whitespace diff lulus.

Perbandingan frontend merupakan pemeriksaan sementara dengan fixture dan stub Inertia/layout; ID otomatis React dinormalisasi. Pemeriksaan preview memakai simulasi lifecycle hook dan scheduler. Keduanya melengkapi suite backend, bukan pengujian browser end-to-end terhadap server produksi.

## Item yang dipertahankan

- H01: command `inspire` tetap ada.
- H02: parameter `$request` pada controller yang disebut audit tetap ada.
- H03: parameter `$actor` pada `CreateDraftWeighing::checkReferences()` tetap ada.
- Parameter policy, alur dokumen legacy, dan pengujian kompatibilitas tetap dipertahankan.
- Cleanup ini tidak menambah dependency, mengubah env, merename route/public API yang masih digunakan, atau mengubah skema database.
