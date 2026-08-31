# Production Backup Local Upgrade Report

## Backup Metadata

- Source file: `C:\Users\Codex\Downloads\csxyalov_yalovakamera_db 22.08.2026.sql`
- File size: `3,538,892` bytes
- Dump tool: phpMyAdmin SQL Dump `5.2.3`
- Dump time: `22 Ağu 2026, 10:30:40`
- Source server marker: MySQL `8.0.46-cll-lve`
- Source database marker: `csxyalov_yalovakamera_db`
- Encoding marker: `utf8mb4`; table collation markers are `utf8mb4_unicode_ci`
- `CREATE DATABASE`, `USE`, `DEFINER`, view, trigger, procedure, function and event statements: not present
- Dump table count: `188`
- No row values or personal data are reproduced in this report.

## Restore Result

PASS. The backup was restored without modifying the source file into the isolated local database:

- Host: `127.0.0.1`
- Port: `3307`
- Server: MariaDB `10.4.32`
- Database: `yalovayazilimsaas_prod_restore_20260822`
- Restored base tables: `188`
- Main XAMPP datadir/port `3306`: not started or touched
- Production/live/SSH/cPanel credentials: not used

## Pre-upgrade Schema

- Base tables: `188`
- Migration table: present
- Key row counts: `firmalar 2`, `users 7`, `firma_kullanicilari 5`, `faturalar 110`, `fatura_kalemleri 166`, `stok_kartlari 214`, `stok_hareketleri 160`, `teknik_servis_kayitlari 116`, `teknik_servis_kalemleri 164`, `finans_hareketleri 111`, `fatura_finans_kapatmalari 104`
- Full declared-FK inventory: `259`
- Schema snapshot was read-only; no data/schema repair was performed.

## Migration History

- Repository migration files: `294`
- Backup migration records: `272`
- Highest recorded batch: `67`
- Production-only records not found in the repository: `7`
- Migration history is therefore not a complete repository-to-production proof.

Production-only records:

```text
2026_08_14_210000_create_stok_partileri_table
2026_08_14_211000_add_parti_fields_to_fatura_kalemleri
2026_08_15_100000_add_parti_dagilimi_to_fatura_kalemleri
2026_08_15_120000_add_mermer_fields_to_stok_partileri
2026_08_15_110000_add_parti_dagilimi_to_siparis_kalemleri
2026_08_15_111000_add_parti_dagilimi_to_barkodlu_satis_kalemleri
2026_08_15_112000_add_parti_seri_to_barkodlu_satis_iade_kalemleri
```

## Pending Migrations

Repository-pending count: `29`.

```text
2026_08_16_020000_add_admin_layout_to_users_table
2026_08_16_120000_create_olculu_stok_altyapisi
2026_08_16_121000_stok_miktar_zincirini_8_basamaga_cikar
2026_08_16_130000_create_fatura_olcu_dagilimlari
2026_08_17_100000_add_olculu_iade_kaynak_baglantilari
2026_08_17_110000_add_olculu_iade_kaynak_foreign_keys
2026_08_18_150000_migrate_stok_karti_olculerini_sadelestir
2026_08_18_160000_add_agirlik_birimi_to_stok_olculeri
2026_08_19_120000_add_finans_reverse_idempotency_unique
2026_08_19_120100_add_alacak_tahsilat_eslesme_unique
2026_08_19_190000_normalize_merkez_depolar
2026_08_20_120000_seed_firma_yoneticisi_full_permissions
2026_08_20_130000_add_duzeltme_kaynagi_to_finans_hareketleri
2026_08_20_130000_seed_stok_sistem_birimleri
2026_08_20_160000_disable_multiple_measure_structure
2026_08_21_090000_restore_olculu_stok_defter_tablolari
2026_08_21_100000_restore_fatura_olcu_dagilimlari
2026_08_22_100000_add_fatura_sinifi_to_faturalar
2026_08_22_120000_add_exchange_difference_snapshot_to_invoice_closures
2026_08_22_120000_remove_parti_parca_sistemi
2026_08_22_130000_restore_serial_tracking_type
2026_08_22_140000_create_kur_farki_hareketleri_table
2026_08_22_150000_remove_remaining_parti_parca_columns
2026_08_22_160000_add_adet_esdegeri_to_fatura_kalemleri
2026_08_22_160000_add_multi_currency_snapshots_to_credit_instruments
2026_08_22_170000_add_fiyat_olcu_snapshot_fields_to_fatura_kalemleri
2026_08_22_170000_add_transaction_currency_to_receivable_installments
2026_08_22_180000_align_teknik_servis_kalemleri_with_fatura
2026_08_22_180000_cleanup_parti_parca_legacy_metadata
```

## Pending Migration Risk

- `SAFE_SCHEMA_ADDITION` / `SAFE_INDEX` / `SAFE_CONSTRAINT`: present in the pending set, but not individually executed because the gate closed first.
- `DATA_BACKFILL` / `DATA_TRANSFORMATION`: measured-stock, unit, depot, currency and tracking migrations are present.
- `DESTRUCTIVE_DROP_COLUMN`: multiple pending migrations drop legacy/admin/measurement/currency/parti-parça columns.
- `DESTRUCTIVE_DROP_TABLE`: `remove_parti_parca_sistemi` contains legacy table drops.
- `CLEANUP`: `cleanup_parti_parca_legacy_metadata` deletes settings, permissions and pivot rows.
- `REQUIRES_DOMAIN_REVIEW`: parti/parça removal, AD/ADET/unit normalization and measurement-chain migrations.

Blocking evidence: `firma_ayarlari` contains `2` parti settings and `yetkiler` contains `2` parti permissions. The pending cleanup migration explicitly deletes these records. This is `HIGH RISK — DATA EXISTS` and `BLOCKED — PRODUCTION DATA DECISION REQUIRED` until an owner approves the intended deletion and its replacement mapping.

## Pre-upgrade Data Integrity

- Declared FK checks: `259`
- Orphan total: `0`
- Critical tenant, invoice, stock, service and invoice-finance orphan checks: `0`
- Open InnoDB transactions: `0`
- Lock-waiting process list entries: `0`
- No data fixes, deletes or normalization writes were made.

## AD/ADET Production State

- `muhasebe_birimler` rows: `3`
- `ADET`: `1` active system unit (`kod=ADET`)
- `AD`: `0`
- `KILO`: `1`; `SAAT`: `1`
- The production clone has no AD alias/canonical pair to validate. The pending unit seed/normalization migrations require domain confirmation before execution.

## Legacy Parti/Parça State

- Legacy parti/parça tables in the backup are empty where present; several expected legacy tables are absent.
- Legacy parti/parça value columns audited in invoice/order/stock paths contain `0` non-empty values in the checked clone columns.
- `stok_takip_tipi` is populated for `214` stock cards and must not be treated as disposable legacy data without confirming the replacement tracking contract.
- The legacy metadata cleanup target is not empty: `2` settings and `2` permissions exist.
- Result: data-bearing cleanup is not safe to run automatically.

## Local Upgrade Gate

```text
LOCAL PRODUCTION UPGRADE GATE: CLOSED
```

Reasons:

1. A pending cleanup migration deletes existing production metadata (`2` settings + `2` permissions).
2. Seven production migration records are not present in the repository, so the complete historical transition cannot be proven.
3. AD/ADET production state has only `ADET`; the expected AD alias/canonical behavior is not established in this clone.

Per the Phase 4 instruction, `php artisan migrate --force` was not run.

## Local Upgrade Result

`NOT RUN — stopped at LOCAL PRODUCTION UPGRADE GATE: CLOSED.` No migration failure was induced and no migration file was changed.

## Post-upgrade Schema

`NOT RUN — no post-upgrade schema exists because migration was intentionally not executed.`

## Fresh Schema Reference

The earlier Phase 2 clean-install report records `294/294` migrations passing on the isolated fresh-schema candidate. A new Phase 4 fresh-vs-upgraded comparison was not started because the production upgrade gate closed before the upgrade step.

## Fresh vs Upgraded Schema Diff

`NOT RUN — upgraded schema was not created.` No diff is being presented as a deployment approval.

## Row Count Comparison

Pre-upgrade snapshot was captured for the critical tables listed above. Post-upgrade comparison is `NOT RUN`; unexpected row loss after migration is therefore not applicable.

## Post-upgrade Data Integrity

`NOT RUN — no upgrade was executed.` Pre-upgrade declared-FK orphan total was `0`.

## Tenant Integrity

- Firms: `2`
- Users: `7`
- Firm memberships: `5`
- Checked tenant-linked critical FKs: `0` orphan rows
- Post-upgrade tenant smoke: not run because gate closed

## Financial Integrity

- Invoices: `110`
- Invoice lines: `166`
- Finance movements: `111`
- Invoice-finance closures: `104`
- Receivable plans: `13`
- Checked financial FK orphans: `0`
- Post-upgrade financial smoke: not run because gate closed

## Stock Integrity

- Stock cards: `214`
- Stock movements: `160`
- Depots: `2`
- Stock depot balances: `0`
- Stock parties: `0`
- Stock serial rows: `0`
- Checked stock FK orphans: `0`
- Post-upgrade stock smoke: not run because gate closed

## Application Boot

Phase 3’s clean application report records the isolated fresh application boot as passing. Phase 4 did not point the application at the restored production clone and did not run boot commands against it, because the local production upgrade gate closed before that stage.

## Read-only Smoke

Pre-upgrade database inspection passed with read-only SQL. Application read-only smoke against the restored production clone was not run; doing so would require a separately approved application environment/configuration step after the migration decision.

## Remaining Risks

- Resolve whether the four existing parti metadata rows are intentionally retired, and document replacement permission/settings mappings before cleanup.
- Reconcile or formally archive the seven production-only migration records.
- Confirm AD/ADET canonical/alias policy for the restored production data; current state has ADET only.
- Review all 29 pending migrations as a dependency-ordered batch after the domain decision, with special attention to data transformations and column/table drops.
- Re-run isolated preflight, then migration, post-upgrade schema diff, row-count comparison, orphan checks and read-only smoke only after approval.
- No production deployment, live migration or rollback action was performed.

## Final Result

```text
1. Backup restore: PASS — isolated local MariaDB 3307, 188 tables
2. Production migrations count: 272
3. Pending migration count: 29
4. Unknown production-only migration records: 7
5. Pending risk: HIGH — destructive cleanup has existing data; domain decision required
6. AD/ADET: ADET=1, AD=0; policy unresolved
7. Parti/parça legacy: value columns checked empty, but 2 settings + 2 permissions remain
8. Local upgrade result: NOT RUN — gate closed before migrate
9. After migration count / 294: NOT APPLICABLE
10. Fresh vs upgraded diff: NOT RUN
11. Critical/high/medium diff: NOT RUN
12. Unexpected row loss: NOT APPLICABLE
13. New orphan count: NOT APPLICABLE; pre-upgrade orphan total 0
14. Tenant integrity: PRE-UPGRADE PASS; post-upgrade not run
15. Financial integrity: PRE-UPGRADE PASS; post-upgrade not run
16. Stock integrity: PRE-UPGRADE PASS; post-upgrade not run
17. Application boot: Phase 3 fresh boot PASS; Phase 4 restored-clone boot not run
18. Read-only smoke: database inspection PASS; application smoke not run
19. Open transactions / pending locks: 0 / 0
20. OVERALL: BLOCKED — review required before local migrate
21. PRODUCTION DEPLOYMENT GATE: CLOSED
22. Report: production-backup-upgrade-report.md
```

`PRODUCTION DEPLOYMENT GATE: CLOSED` is a local decision only. No live deployment was attempted. Phase 5 deployment/rollback planning must remain a separate, later phase after this report’s blockers are reviewed and resolved.

## Phase 4.1 — History Reconciliation and Domain Decisions

This section is based on read-only inspection of the restored clone and repository history. No migration, seeder, data update, delete, or migration-table edit was executed in Phase 4.1.

### 7 Production-only migrations

All seven records were found in Git history at commit `0bcebc02c7560942fe0313483090cf00ffd448fa` (`chore: initial project snapshot`, 2026-08-22). The original files were recovered for inspection only; they were not restored to the repository.

| Migration | Git commit | Original purpose / schema evidence | Later replacement or removal | Status |
|---|---|---|---|---|
| `2026_08_14_210000_create_stok_partileri_table` | `0bcebc0` | Added `stok_kartlari.stok_takip_tipi` and its `(firma_id, stok_takip_tipi)` index. Despite its filename, this is the origin of the current serial/simple tracking field; it did not create a party table in the recovered body. | Current `StokKarti` contract and `restore_serial_tracking_type` preserve/recreate the field. | HISTORICALLY RECONCILED |
| `2026_08_14_211000_add_parti_fields_to_fatura_kalemleri` | `0bcebc0` | Guard-only migration; no columns, indexes or FKs were added in the recovered body. | Later parti/parça cleanup migrations remove any legacy columns found by schema inspection. | HISTORICALLY RECONCILED |
| `2026_08_15_100000_add_parti_dagilimi_to_fatura_kalemleri` | `0bcebc0` | Empty `up`/`down`; no schema effect in the recovered body. | Same cleanup chain. | HISTORICALLY RECONCILED |
| `2026_08_15_120000_add_mermer_fields_to_stok_partileri` | `0bcebc0` | Added optional marble/part fields to `stok_parcalari` when that table existed. | `remove_parti_parca_sistemi` drops legacy tables; remaining-column cleanup handles survivors. | HISTORICALLY RECONCILED |
| `2026_08_15_110000_add_parti_dagilimi_to_siparis_kalemleri` | `0bcebc0` | Added `siparis_kalemleri.parca_kodu` and `parca_dagilimi`. | `remove_parti_parca_sistemi` / `remove_remaining_parti_parca_columns`. | HISTORICALLY RECONCILED |
| `2026_08_15_111000_add_parti_dagilimi_to_barkodlu_satis_kalemleri` | `0bcebc0` | Added `muhasebe_barkodlu_satis_kalemleri.parca_kodu` and `parca_dagilimi`. | Same cleanup chain. | HISTORICALLY RECONCILED |
| `2026_08_15_112000_add_parti_seri_to_barkodlu_satis_iade_kalemleri` | `0bcebc0` | Added `parca_kodu`, `parca_dagilimi` and `seri_nolari` to barcode-sale return lines. | Parti/parça cleanup removes only legacy fields; serial tracking remains an active separate contract. | HISTORICALLY RECONCILED |

Conclusion: `7 / 7` are historically reconciled. Their presence in the production `migrations` table is not, by itself, a Laravel execution blocker. The current schema and the later cleanup chain are the relevant convergence checks.

### Parti metadata decision

The four records are:

| Type | Key/code | Row count | Active repository reference | Classification |
|---|---|---:|---|---|
| Setting | `stok_parti_telegram_aktif_mi` | 1 | Only referenced by the cleanup migration | SAFE TO CLEAN |
| Setting | `stok_parti_telegram_uyari_gun` | 1 | Only referenced by the cleanup migration | SAFE TO CLEAN |
| Permission | `stok_parti.goruntule` | 1 | Only referenced by the cleanup migration | SAFE TO CLEAN |
| Permission | `stok_parti.duzelt` | 1 | Only referenced by the cleanup migration | SAFE TO CLEAN |

The two permissions have role-pivot references (`5` and `4` rows respectively); those pivots are also explicitly removed by the cleanup migration. No user-permission pivot rows were found. Given the domain decision that the old parti/parça system is retired, this is intentional cleanup, not an unresolved active-module dependency.

### `stok_takip_tipi` decision

`stok_takip_tipi` is classified as `ACTIVE CURRENT CONTRACT`, not legacy:

- `StokKarti` defines `basit` and `seri` as current constants.
- `StokHareketServisi` uses it to enforce serial tracking behavior.
- `BarkodluSatisServisi`, order-payment flow, stock count UI, stock-card forms and views read it.
- The model validation rejects serial tracking together with incompatible measured-stock configurations.
- The field is fillable and indexed by tenant/type.

Production clone distribution:

| `stok_takip_tipi` | Cards |
|---|---:|
| `basit` | 214 |
| `seri` | 0 |
| `parti` | 0 |
| empty/null | 0 |

Decision: `PRESERVE`. Do not remove, rename or blanket-map this field. The pending parti cleanup migration’s `parti -> basit` update is currently a no-op because no card has `parti`. The later serial-field migration also skips creation because the column already exists.

### AD/ADET reference map

Current system-unit row:

- `muhasebe_birimler.id = 1`
- `kod = ADET`
- `ad = Adet`

The restored production schema has no declared FK column referencing `muhasebe_birimler`, and no current `*_birim_id`/`*_olcu_id` column exists. Therefore current active FK references to ADET ID `1` are `0`.

The current text snapshot fields are not FK references:

| Table | Column | ADET/AD normalized text count | Classification |
|---|---|---:|---|
| `stok_kartlari` | `birim` | 214 (`AD` 210, `ADET` 4) | Historical/unit snapshot |
| `fatura_kalemleri` | `birim` | 166 (`AD` 166) | Historical invoice-line snapshot |
| `muhasebe_barkodlu_satis_kalemleri` | `birim` | 2 (`AD` 2) | Historical sale-line snapshot |
| `teklif_kalemleri` | `birim` | 15 (`AD` 11, `ADET` 4) | Historical offer-line snapshot |
| `teknik_servis_kalemleri` | `birim` | 164 (`AD` 164) | Historical service-line snapshot |

These text snapshots must not be rewritten as part of an ID transition.

### Canonical AD simulation

Repository contract:

- `BirimKodResolver::normalize('ADET')` returns canonical `AD`.
- `acceptedCodes('AD')` returns `AD` and `ADET`.
- The resolver therefore supports both codes for lookup.
- `MuhasebeOlcuBirimleriSeeder` preserves an existing ADET-only row and does not create an AD twin when run by itself.
- However, pending migration `2026_08_20_130000_seed_stok_sistem_birimleri` uses `updateOrInsert(['kod' => 'AD', ...])`; with only ADET present, it creates a new AD row. It does not rename ID `1` and does not populate future FK columns.

Expected pending-migration-only result: ADET ID `1` remains, a new AD row is created (normally next ID `4`), and the system-unit count becomes `4`. There is no duplicate database key error because the unique key is `(tanim_firma_kapsami, kod)`, and `AD` and `ADET` are distinct values. This leaves two rows with the same business meaning until a deliberate transition is performed.

Recommended transition: `A) REQUIRES DATA MIGRATION` — retain ID `1`, change only its code from `ADET` to canonical `AD`, retain display `Adet`, and keep all historical text snapshots unchanged. This avoids creating a semantic twin and preserves any future FK identity. It still requires an explicit checkpoint because external SQL/integration consumers may use the literal `ADET` code. Option B (create AD, move FKs, retain ADET alias) is less safe here because no current FK graph exists, future nullable FK columns are introduced later, and it creates a duplicate semantic system unit.

Current result: `AD canonical transition: REQUIRES DATA MIGRATION`. No transition was executed.

### 29 pending migration dependency graph

The dependency review is grouped in execution order. `Safe now` means safe against the restored clone in isolation; the complete chain remains blocked by the unresolved unit transition.

| Migration | Depends on / produces | Consumes | Destructive / transform | Safe now |
|---|---|---|---|---|
| `add_admin_layout_to_users_table` | users → `admin_layout` | none | drops column on down only | YES; target absent in clone |
| `create_olculu_stok_altyapisi` | units + stock cards → measured columns, `stok_olculeri` and future unit FKs | stock cards, unit IDs | schema addition | NO; unit transition first |
| `stok_miktar_zincirini_8_basamaga_cikar` | existing numeric schema | amount columns | precision change | CONDITIONAL |
| `create_fatura_olcu_dagilimlari` | measured-stock tables | invoice lines | adds snapshot/table; down drops columns | CONDITIONAL |
| `add_olculu_iade_kaynak_baglantilari` | invoice measurement tables | invoice return links | adds/removes link columns | CONDITIONAL |
| `add_olculu_iade_kaynak_foreign_keys` | prior return-link columns | invoice/measurement IDs | FK addition | CONDITIONAL |
| `migrate_stok_karti_olculerini_sadelestir` | measured tables | legacy dimensions | backfill + drops `en_cm`, `boy_cm`, `kalinlik_cm`, `urun_agirligi` | NO; data checkpoint required |
| `add_agirlik_birimi_to_stok_olculeri` | `stok_olculeri` | weight measurements | backfill `kg` | CONDITIONAL |
| `add_finans_reverse_idempotency_unique` | finance movements | reverse IDs | unique index | YES after duplicate assertion |
| `add_alacak_tahsilat_eslesme_unique` | receivable matches | match keys | unique index | YES after duplicate assertion |
| `normalize_merkez_depolar` | depots | `MERKEZ` rows | data normalization | CONDITIONAL; snapshot first |
| `seed_firma_yoneticisi_full_permissions` | roles/permissions | permission catalog | data upsert/pivot inserts | CONDITIONAL |
| `add_duzeltme_kaynagi_to_finans_hareketleri` | finance movements | correction source | schema addition | YES if absent |
| `seed_stok_sistem_birimleri` | unit table | unit codes | creates AD/KGM rows | NO; AD transition first |
| `disable_multiple_measure_structure` | measured stock columns | `olcu_yapisi` | data transform `coklu -> sabit` | CONDITIONAL; snapshot first |
| `restore_olculu_stok_defter_tablolari` | `stok_olculeri`, depots, movements | measured balances/distributions | table/FK creation | CONDITIONAL |
| `restore_fatura_olcu_dagilimlari` | measured stock ledger tables | invoice lines | table/FK creation | CONDITIONAL |
| `add_fatura_sinifi_to_faturalar` | invoices | invoice rows | schema addition | YES if absent |
| `add_exchange_difference_snapshot_to_invoice_closures` | invoice closures | closure rows | schema addition | YES if absent |
| `remove_parti_parca_sistemi` | measured ledger tables if present | legacy columns/tables and `stok_takip_tipi=parti` | drops legacy schema; maps only `parti` | YES after checks; current data is empty/no-op |
| `restore_serial_tracking_type` | stock cards | `stok_takip_tipi` | creates field only if absent | YES; current field preserved |
| `create_kur_farki_hareketleri_table` | invoices/finance/closures | related IDs | table/FK creation | CONDITIONAL |
| `remove_remaining_parti_parca_columns` | legacy document columns | order/sales/invoice columns | drops columns | YES after non-null assertions |
| `add_adet_esdegeri_to_fatura_kalemleri` | invoice lines | quantity snapshots | schema addition | CONDITIONAL |
| `add_multi_currency_snapshots_to_credit_instruments` | currency-bearing finance tables | historical amounts | schema addition | CONDITIONAL |
| `add_fiyat_olcu_snapshot_fields_to_fatura_kalemleri` | units + invoice lines | price unit fields | FK/schema addition | NO; AD transition first |
| `add_transaction_currency_to_receivable_installments` | receivable tables | installment rows | schema addition | CONDITIONAL |
| `align_teknik_servis_kalemleri_with_fatura` | service lines + units | service-line history | schema addition followed by destructive down | CONDITIONAL; snapshot first |
| `cleanup_parti_parca_legacy_metadata` | settings/permissions/pivots | four retired metadata keys/codes | deletes metadata and pivots | YES only under confirmed domain decision |

Dependency review result: `FAIL` for an automatic production upgrade because measured-stock/unit work must be preceded by the AD transition and data checkpoints. The parti removal subset is safe after the stated domain decision.

### Parti/parça removal target audit

- `remove_parti_parca_sistemi`: existing legacy tables `stok_partileri` and `stok_hareketi_partileri` have `0` rows; `stok_parcalari`, `stok_hareketi_parcalari`, `stok_parca_islem_loglari` and `stok_parti_kimlikleri` are absent. Legacy target columns present in invoice/order/sales/stock tables have `0` non-null/non-empty values. Classification: `SAFE` after the metadata decision.
- `remove_remaining_parti_parca_columns`: all present target columns (`parti_no`, `parti_dagilimi`, `parca_no` and related fields) have `0` non-null/non-empty values; measurement distribution tables are absent. Classification: `SAFE`.
- `cleanup_parti_parca_legacy_metadata`: the four keys/codes exist and their role pivots contain `9` rows total. They have no active repository references outside the cleanup migration and the old system is explicitly retired. Classification: `SAFE` under the domain decision; deletion must still be included in the pre-migration row-count checkpoint.
- `stok_takip_tipi` is explicitly excluded from removal and remains `PRESERVE`.

### Migration history strategy

`YES` — the seven production-only migration rows may remain as historical records. Laravel does not need repository files for already-recorded migrations to consider them run; deleting rows or re-adding old files would create more risk. Schema convergence, explicit data assertions and the current repository’s pending migration chain are the correctness criteria, not name-set identity.

### Phase 4.2 preflight plan

1. Keep the seven production-only rows unchanged; do not restore files or edit `migrations`.
2. Record the four cleanup keys, permission IDs and role-pivot counts; run cleanup only after the approved domain decision.
3. Preserve all 214 `stok_takip_tipi` values; assert `parti=0`, `seri=0`, `basit=214` immediately before and after migration.
4. Before `seed_stok_sistem_birimleri`, perform the approved ID-1 `ADET -> AD` transition, or explicitly approve the two-row alias strategy. Assert unique unit rows and resolver behavior.
5. Snapshot row counts and non-null counts before each destructive measured-stock, parti/parça, currency and technical-service migration.
6. Run pending migrations in dependency order with checkpoints after measured schema creation, unit normalization, legacy removal, finance/currency additions and technical-service alignment.
7. After each checkpoint, assert no row loss, no new FK orphans, unchanged historical text snapshots, unchanged stock tracking distribution, and expected unit IDs/counts.
8. Only after all assertions pass, build the fresh-vs-upgraded schema diff and reconsider the local production upgrade gate. Live deployment remains out of scope.

## Phase 4.1 Final Result

```text
PHASE 4.1 RESULT

Production-only migrations: 7
Historically reconciled: 7 / 7
Parti metadata: SAFE TO CLEAN
stok_takip_tipi: PRESERVE
ADET current row: ID = 1; active FK references = 0
AD canonical transition: REQUIRES DATA MIGRATION
Pending migration dependency review: FAIL
Parti removal migrations: SAFE
Migration history strategy: SAFE
LOCAL PRODUCTION UPGRADE GATE: CLOSED
Migration executed in Phase 4.1: NO
```

## Phase 4.2 — Controlled Production Clone Upgrade

### Isolation and fresh restore

- Source backup: `C:\Users\Codex\Downloads\csxyalov_yalovakamera_db 22.08.2026.sql`
- SHA-256: `0905F63A1BC14076D0CE937F3A5B3A44575749A7B9A33BD053DAEB1E52765F17`
- Local target only: `127.0.0.1:3307 / yalovayazilimsaas_prod_upgrade_phase42_20260822`
- Restore assertions: 188 tables, 272 migration rows, ADET ID 1, AD absent, `stok_takip_tipi basit=214`, 259 declared FK checks and 0 orphans — PASS.
- `PHASE42_PRE_UPGRADE` schema and table-count snapshots: `output/phase42/PHASE42_PRE_UPGRADE_schema.sql` and `output/phase42/PHASE42_PRE_UPGRADE_row_counts.tsv`.

### Controlled AD transition

The existing pending `2026_08_20_130000_seed_stok_sistem_birimleri` migration now invokes an explicit transition service before seeding AD. The service locks AD/ADET rows, blocks an unexpected AD+ADET state, asserts exactly one ADET row with ID 1/display `Adet`, asserts zero active FK references, and changes only `kod: ADET -> AD` inside a transaction. Fresh/empty state remains a no-op before the migration seeds canonical AD. A second execution with AD-only state is a no-op.

Result on the freshly restored clone: AD count 1, ADET count 0, AD ID 1, display `Adet`. Historical unit text distributions in stock, invoice, offer, service and barcode-sale lines were unchanged. SQLite and MariaDB regression runs both passed: 4 tests, 9 assertions.

### Local upgrade gate

All Phase 4.1 decisions and Phase 4.2 assertions passed before migration: history 7/7 reconciled, parti metadata approved for cleanup, tracking field preserved, semantic unit twin absent, commercial snapshots unchanged, pre-upgrade orphan total zero and 29 migrations pending in the reviewed order.

`LOCAL PRODUCTION UPGRADE GATE: OPEN`

### Migration failure

The local `php artisan migrate --force` run completed 19 of the 29 pending migrations, then stopped as required.

```text
Migration: 2026_08_22_120000_remove_parti_parca_sistemi
SQLSTATE: HY000 / MariaDB error 1553
Table: stok_hareketleri
Column/FK/index: parti_id / stok_hareketleri_parti_id_foreign
Existing data: legacy parti tables were empty and audited legacy values were empty; failure is DDL ordering/driver detection, not a non-empty value assertion
Root cause: the migration detects foreign keys only when driverName === 'mysql'. Laravel reports 'mariadb', so stok_hareketleri_parti_id_foreign is not dropped before dropColumn('parti_id'). MariaDB rejects dropping the FK-required index/column.
```

No automatic repair, retry, rollback, fresh-reference build, schema diff, application boot or post-upgrade smoke was performed after this failure. The failed migration was not recorded as Ran. DDL from earlier completed migrations remains in this disposable local clone, so it is not a valid final upgraded schema.

At stop: 291 total migration-table rows, including 284/294 current repository migrations and 7 historical extra rows; 10 current migrations remain pending. Critical commercial row counts remained unchanged from the snapshot. AD ID 1 and `stok_takip_tipi basit=214` remained intact. Open transactions were 0 and no waiting user lock was shown.

```text
PHASE 4.2 RESULT

Fresh restore: PASS
AD precondition: PASS
AD transition: PASS
AD ID preserved: PASS
Semantic twin: 0
Historical text snapshots unchanged: PASS
Parti cleanup: FAIL — migration stopped before cleanup completed
stok_takip_tipi preserved: PASS at failure checkpoint
Local production upgrade: FAIL
Current repository migrations marked Ran: 284 / 294
Historical extra migration records: 7 / 7
Unexpected commercial row loss: 0 at failure checkpoint
Intentional legacy cleanup: FAIL / NOT COMPLETED
New orphan violations: NOT RUN as final post-upgrade audit; pre-upgrade 0
Fresh schema: NOT RUN after migration failure
Fresh vs upgraded schema: NOT RUN
Critical schema diffs: NOT RUN
High schema diffs: NOT RUN
Medium schema diffs: NOT RUN
Tenant integrity: NOT RUN post-upgrade
Financial integrity: NOT RUN post-upgrade
Stock integrity: NOT RUN post-upgrade
Application boot: NOT RUN
Read-only smoke: NOT RUN
Open transactions: 0
Pending locks: 0 observed

OVERALL: FAIL
PRODUCTION DEPLOYMENT GATE: CLOSED
```

No production connection, live migration, deployment, backup mutation, historical text rewrite, historical migration-row deletion or `stok_takip_tipi` removal occurred.

## Phase 4.2.1 — MariaDB FK Compatibility Repair and Upgrade Retry

### FIX-MIG-01

- File: `laravel-core/database/migrations/2026_08_22_120000_remove_parti_parca_sistemi.php`
- Original problem: MariaDB reported `SQLSTATE HY000 / error 1553` while dropping `stok_hareketleri.parti_id`; the existing `stok_hareketleri_parti_id_foreign` FK was not detected.
- Root cause: Laravel MariaDB connection reports driver `mariadb`, while the migration accepted only `mysql` for `information_schema.statistics` and `information_schema.key_column_usage` inspection.
- Minimal change: both metadata branches now accept `in_array($driver, ['mysql', 'mariadb'], true)`.
- Why safe for MySQL: the existing MySQL branch and SQL metadata queries remain unchanged in behavior.
- Why safe for MariaDB: MariaDB exposes the same metadata used by these queries; the FK is now dropped before its column.
- Fresh-install result: PASS; the migration completed in the fresh `294/294` chain.
- Production-clone result: PASS; the former 1553 failure did not recur.

### FIX-MIG-02

- File: `laravel-core/database/migrations/2026_08_22_150000_remove_remaining_parti_parca_columns.php`
- Original problem: the same MySQL-only FK metadata branch could fail on MariaDB when removing remaining legacy columns.
- Root cause: `DB::getDriverName() === 'mysql'` excluded MariaDB.
- Minimal change: accepted `mysql` and `mariadb` only for that FK metadata branch.
- Why safe for MySQL/MariaDB: the branch is specifically the shared `information_schema` FK inspection path; no domain behavior or column names changed.
- Fresh-install result: PASS.
- Production-clone result: PASS.

### Other same-pattern MariaDB fixes

The pending-migration scan found no other pending category-A compatibility bug. Other `mysql` checks were genuinely MySQL-specific or already had a `mysql,mariadb` branch; they were not changed.

### Targeted FK regression

PASS. A disposable MariaDB database recreated `stok_hareketleri_parti_id_foreign`; the metadata query detected the exact FK name, then FK removal succeeded before `parti_id` removal. `MariaDB error 1553` did not occur.

### Fresh migration regression

PASS: empty `yalovayazilimsaas_fresh_phase421_20260822` completed `294 / 294` current migrations. The minimum SaaS seed chain, including `MuhasebeOlcuBirimleriSeeder`, completed successfully.

### Fresh production restore and pre-upgrade state

PASS: new clone `yalovayazilimsaas_prod_phase421_20260822` on `127.0.0.1:3307` restored 188 tables and 272 original migration records. Preconditions matched the prior backup analysis: ADET ID 1, AD 0, `basit=214`, `parti=0`, `seri=0`, and pre-upgrade orphan total 0.

### Controlled AD transition

PASS. Inside a local transaction, only `muhasebe_birimler.id=1.kod` changed from `ADET` to `AD`; display remained `Adet`, ID remained `1`, and historical text snapshots were not changed. Post-transition state: `AD=1`, `ADET=0`, semantic twin `0`.

### Local production upgrade

PASS. All 29 pending migrations completed on the new local clone. Current repository migration filenames are `294/294` Ran; the seven historical production-only rows remain preserved, for a total of `301` migration rows. No live or main XAMPP database was used.

### Post-upgrade checks

- Commercial row counts: unchanged for the required commercial tables; intentional cleanup was `firma_ayarlari -2`, `yetkiler -2`, `rol_yetkileri -6` (the cleanup’s two permissions had 5 and 4 pre-upgrade role references; final role-pivot count also reflects the permission matrix migration’s expected changes).
- `stok_takip_tipi`: column present; `basit=214`, `parti=0`, `seri=0`.
- Parti cleanup: PASS; target legacy tables/columns and four metadata keys/codes were removed; no legacy commercial values were non-empty.
- Post-upgrade declared FK orphan audit: `0` across `282` final FK relationships.
- Tenant cross-ownership checks: `0` invoice/cari, stock movement/stock card, technical-service/cari and finance/cari violations.
- Financial checks: invoice lines, closures-to-invoice, closures-to-finance missing references `0`.
- Stock checks: movement-to-stock missing references `0`, firm mismatch `0`.
- Application boot: `php artisan about` PASS; `php artisan route:list` PASS.
- Read-only application smoke: firmalar `2`, cariler `107`, stock cards `214`, invoices `110`, finance movements `111`, technical-service records `116`; PASS.
- AD regression: PASS on disposable MariaDB state for ADET-only transition/ID preservation, AD-only no-op, second execution no-op, and AD+ADET duplicate-state block.
- Final open transactions / lock waits / lock processes: `0 / 0 / 0`.

### Fresh vs upgraded schema diff

Fresh reference: `yalovayazilimsaas_fresh_phase421_20260822` with 294 migrations and minimum seed. Upgraded clone: `yalovayazilimsaas_prod_phase421_20260822` with production data, AD transition and all pending migrations. Both contain `191` base tables and table options/charset/collation checks had no differences.

The schema is not yet converged:

- `DIFF-001` — `170` foreign-key definitions exist in fresh but not in upgraded production. Severity: `CRITICAL`. This is structural referential-integrity drift, even though current orphan checks are zero. Likely cause: the backup/current production history contains older schemas where these FKs were absent and the current migration chain treats those migrations as already run. Action: reconcile these FK definitions in a separate approved schema-repair phase; do not silently add them during deployment.
- `DIFF-002` — `14` fresh-only index entries and `10` upgraded-only index entries. Severity: `HIGH` for fresh-only performance/constraint indexes and `MEDIUM` for renamed/legacy index-shape differences. Action: produce a named index-by-index reconciliation before deployment.
- `DIFF-003` — upgraded-only columns: `fatura_kalemleri.uretim_tarihi`, `fatura_kalemleri.son_kullanma_tarihi`, `muhasebe_barkodlu_satis_iade_kalemleri.seri_nolari`. Severity: `MEDIUM`. These are production schema additions not present in the current fresh reference; preserve data, then decide whether current migrations or fresh baseline should converge.

Diff totals: `CRITICAL=1` category (`170` FK objects), `HIGH=1` category (`14` fresh-only index entries), `MEDIUM=2` categories (`10` index entries + `3` columns). The required critical/high threshold is not met.

### Phase 4.2.1 final result

```text
PHASE 4.2.1 RESULT

MariaDB FK compatibility fix: PASS
Other same-pattern MariaDB fixes: 1 additional pending cleanup migration fixed
Targeted FK regression: PASS
Fresh migrations: 294 / 294
Fresh production restore: PASS
AD transition: PASS
AD ID preserved: PASS
AD final: AD=1, ADET=0, semantic twin=0
Local production migration: PASS
Current repository Ran: 294 / 294
Historical extra records: 7 / 7 preserved
Parti cleanup: PASS
stok_takip_tipi: PASS — basit=214, parti=0, seri=0
Unexpected commercial row loss: 0
New orphan violations: 0
Fresh schema: PASS
Fresh vs upgraded schema: FAIL — 170 FK, 14 index and 3 column differences
Critical diffs: 170 FK objects
High diffs: 14 fresh-only index entries
Medium diffs: 10 index entries + 3 columns
Tenant integrity: PASS
Financial integrity: PASS
Stock integrity: PASS
Application boot: PASS
Read-only smoke: PASS
AD regression: PASS
Open transactions: 0
Pending metadata locks: 0
OVERALL: FAIL — schema convergence blocked
PRODUCTION DEPLOYMENT GATE: CLOSED
```

The local migration retry is complete, but this is not a production deployment approval. The 170 missing production foreign keys must be reconciled and reviewed in a separate phase before any deployment/rollback plan is considered.

## Phase 4.3 — Schema Convergence Audit

This phase was read-only. No production server or production database was contacted. No migration, schema repair, FK/index/column change, migration-history change, schema dump or deployment was performed.

### Compared databases

| Field | A — fresh reference | B — upgraded production clone |
|---|---|---|
| Database | `yalovayazilimsaas_fresh_phase421_20260822` | `yalovayazilimsaas_prod_phase421_20260822` |
| Host / port | `127.0.0.1:3307` | `127.0.0.1:3307` |
| MariaDB | `10.4.32` | `10.4.32` |
| Base tables | 191 | 191 |
| Columns | 2962 | 2965 |
| Referential constraints | 452 | 282 |
| Non-primary index definitions | 883 | 884 |
| Migration state | 294/294 + required seed | 294/294 + 7/7 historical rows |

A is the empty database after all 294 current migrations and the required seed. B is the restored backup after the controlled AD transition and all 29 pending migrations. The comparison was not against an old or partial clone.

### Diff-tool verification

The 170 FK result is real, not a constraint-name or metadata artifact. The comparison was repeated using the functional signature `(child table, child column(s), parent table, parent column(s), ON DELETE, ON UPDATE)`, ignoring constraint names. It produced 170 fresh-only signatures and zero production-equivalent signatures. `SHOW CREATE TABLE` confirmed the result on representative tenant, finance, stock, technical-service and barcode-sales tables, including `firmalar`, `finans_hareketleri`, `stok_hareketleri`, `teknik_servis_kayitli_cihazlar` and `muhasebe_barkodlu_satis_iade_kalemleri`.

### FK root-cause classification

| Category | Count | Interpretation |
|---|---:|---|
| A — upgrade path skipped FK creation | 0 | No isolated conditional/schema-path omission was proven. |
| B — historical production schema difference | 0 | Covered by the create-table historical-gap classification below. |
| C — fresh-only FK from early create-table migration | 169 | Historical production tables were created without these constraints; later migrations do not backfill them. |
| D — driver / MariaDB detection bug | 1 | `muhasebe_doviz_kurlari_firma_id_foreign`; source has a MySQL-only branch. |
| E — intentionally optional FK | 0 | None approved as optional. |
| F — fresh schema bug / unnecessary FK | 0 | None proven unnecessary. |
| G — diff-tool artifact | 0 | Functional comparison and `SHOW CREATE TABLE` disproved this. |
| H — unknown | 0 | All 170 were assigned a source family. |

The 169 category-C rows are the create-table historical gap: fresh creates each table and FK together, while the upgraded clone sees the historical table as already existing. The one category-D row is sourced to `2026_03_30_130000_create_muhasebe_doviz_kurlari_table.php`. The missing FKs include `CASCADE`, `SET NULL` and `RESTRICT`; orphan-free data does not approve copying delete semantics blindly, so application relations, raw joins and delete flows require per-table review.

### FK safety and repair groups

- FK safe to add by current orphan check: **170**.
- FK blocked by orphan data: **0**; total orphan rows: **0**.
- Group A — add to upgraded production after semantic review: **170**.
- Group B — remove/change from fresh: **0**.
- Group C — unresolved domain decision: **0**, while cascade/domain approval remains required for Group A.

No repair migration was written. A future repair should be split by domain (core tenant/accounting, stock/depot/measurement, technical service, personnel, restaurant, ecommerce/payment), with preconditions, zero-orphan assertions, implicit-index checks, MySQL 8 and MariaDB 10.4 tests, and per-group rollback.

### Index convergence

The original 14 fresh-only rows normalize to 5 functional fresh-only shapes; the remaining differences are name/definition-level. The 14 result is: **3 required missing-index candidates**, **2 functionally equivalent name pairs**, **0 fresh redundant/artifact**, plus FK-implicit/performance-only shapes. The 10 medium differences are mixed renamed/equivalent and real fresh-only row-level differences; no automatic repair decision is made. Six upgraded-only functional shapes are retained as legacy/implicit indexes. No index is added, removed or renamed in this phase.

### Production-only columns

| Table / column | Definition and data | Current usage | Decision |
|---|---|---|---|
| `fatura_kalemleri.uretim_tarihi` | `date`, nullable, default `NULL`; 166 rows / 0 non-null | Stock-card forms, models and expiry flows | Production-only but required; should exist in fresh |
| `fatura_kalemleri.son_kullanma_tarihi` | `date`, nullable, default `NULL`; 166 rows / 0 non-null | Stock-card forms, models and expiry flows | Production-only but required; should exist in fresh |
| `muhasebe_barkodlu_satis_iade_kalemleri.seri_nolari` | `longtext`, nullable, default `NULL`; 0 rows / 0 non-null | Barcode sale/return service, model and Livewire flows | Production-only but required; should exist in fresh |

These columns are actively referenced and are not safe legacy debris. They must be preserved; no removal was attempted.

### Canonicality and final gate

Fresh is **PARTIAL**, not a complete production canonical: it is a valid clean-install baseline and passes application/tests, but it does not yet encode the 169 historical create-table FK gaps, the MariaDB driver issue, or the three required production columns. Production must not be blindly forced to fresh until those decisions are made.

## SCHEMA CONVERGENCE AUDIT — FINAL SUMMARY

1. Fresh FK count: **452**
2. Upgraded production FK count: **282**
3. Confirmed real missing FK: **170**
4. Functionally equivalent FK: **0**
5. Diff artifacts: **0**
6. Upgrade-path skipped FK: **0**
7. Create-table historical gap: **169**
8. Driver-related FK issue: **1**
9. Intentional optional FK: **0**
10. Fresh-schema bug/unnecessary FK: **0**
11. Unknown: **0**
12. FK safe to add: **170**
13. FK blocked by orphan data: **0**
14. Repair Group A: **170**
15. Repair Group B: **0**
16. Repair Group C: **0 unresolved; semantic review still required**
17. Fresh-only indexes: **14 row-level / 5 functional; 3 required candidates; 2 equivalent pairs; 0 redundant/artifact**
18. Medium index differences: **10; mixed renamed/equivalent and real fresh-only differences; no repair performed**
19. Production-only columns: **3; all production-only-but-required / should exist in fresh; 0 non-null rows**
20. Fresh schema canonical: **PARTIAL**
21. Schema repair readiness: **BLOCKED**
22. Changed files: **only `production-backup-upgrade-report.md` in Phase 4.3; no schema or migration file changed**
23. Report path: **`C:\Users\Codex\Desktop\yalova-yazilimsaas\production-backup-upgrade-report.md`**

**PRODUCTION DEPLOYMENT GATE: CLOSED**

## Phase 4.4 — Delete Flow / Cascade Depth / Index Decision Completion

This is a read-only evidence completion checkpoint. The 55 CASCADE rows were investigated against the repaired 170-row FK manifest; the 115 preliminary KEEP rows were not reclassified.

| Measure | Result |
|---|---:|
| FK scope | 55 |
| FK structurally/data investigated | 55/55 |
| Orphan total | 0 |
| Delete behavior resolved | 0/55 |
| Cascade depth completed | 0/55 |
| Domain decision completed | 0/55 |
| Critical evidence complete | 0/30 |
| Critical unresolved | 30 |

No complete parent-model/delete-entry-point mapping or reliable downstream cascade graph was established. Therefore no CASCADE, RESTRICT / NO ACTION, SET NULL, or SKIP recommendation is approved:

| Technical recommendation | Count |
|---|---:|
| CASCADE | 0 |
| RESTRICT | 0 |
| SET NULL | 0 |
| SKIP | 0 |
| UNRESOLVED | 55 |

All three index candidates were inspected, but none reached a final decision: REQUIRED '0', REJECT '0', UNRESOLVED '3'; completion '0/3'. MariaDB and MySQL compatibility evidence remains incomplete for the still-undecided repair behavior.

The canonical FK evidence CSV is 'output/phase44/fk-semantic-review.csv' with 170 rows: 115 KEEP-PRELIMINARY and 55 DECISION. No schema, data, migration, application code, or production state was changed.

**FAZ 4.4: STILL OPEN**  
**FAZ 4.5: NOT STARTED**  
**PRODUCTION DEPLOYMENT GATE: CLOSED**

## Phase 4.4 Evidence Collection Execution — Technical Findings

This is still an evidence-collection continuation; it does not close Phase 4.4. Only the 55 `DECISION` rows were investigated. No schema, data, migration or application-code change occurred.

### Evidence collection counts

- DECISION FK scope: **55/55**; list matches the structural CSV scope.
- Repository code/table reference search: **55/55** rows have repository references. This includes migration/table references; it is not, by itself, delete-domain proof.
- Test-table/domain references: **20/55**. The remaining rows are explicitly `NO TEST FOUND` for this pass; test absence is not treated as a decision.
- Local clone data queries: **55/55** completed with child/parent totals, null/non-null counts, distinct FK counts and maximum direct child-per-parent aggregation.
- Orphan result: **0** across the 55 decision rows.
- Evidence-complete under the required definition (schema/data plus code/domain conclusion): **0**. Every decision row still has an unresolved hard-delete/cascade contract.

Representative local-clone data evidence: `firma_ayarlari.firma_id` has `39` child rows and maximum `39` children for one parent; `stok_barkodlari.stok_id` has `152` child rows with maximum `1` per referenced stock; `rol_yetkileri.yetki_id` has `542` child rows and maximum `8` per referenced permission; `teknik_servis_ariza_kayitlari.teknik_servis_kaydi_id` has `124` child rows and maximum `3` per service record. These values show cascade impact but do not authorize deletion semantics.

### Critical FK status

All **30/30** critical rows received schema/data/code-reference investigation. None is fully complete under the required standard because exact parent hard-delete/forceDelete entry points, downstream cascade depth, and test protection were not all proven for each row. Critical evidence complete: **0/30**; critical unresolved: **30**.

### Index evidence execution

The three candidates were checked against the real-data upgraded local clone. The ecommerce table and technical-service history table contain `0` rows; the device table contains `111` rows, with `cihaz_id` distinct `11` and the combined identity distinct `75`. Existing index coverage and read-only `EXPLAIN` were captured. The optimizer used existing keys or a full scan under low data volume, so no REQUIRED/REJECT decision is justified.

- Index candidates inspected: **3/3**.
- Technical REQUIRED: **0**.
- Technical REJECT: **0**.
- Technical UNRESOLVED: **3**.
- Index evidence complete: **0/3** because cardinality/write-cost/query-shape proof is incomplete for the empty candidate tables.

The per-row FK evidence fields are in [fk-semantic-review.csv](C:\Users\Codex\Desktop\yalova-yazilim-saas\output\phase44\fk-semantic-review.csv); the investigation summary is in [decision-fk-evidence.md](C:\Users\Codex\Desktop\yalova-yazilim-saas\output\phase44\decision-fk-evidence.md), and index details are in [index-evidence.md](C:\Users\Codex\Desktop\yalova-yazilim-saas\output\phase44\index-evidence.md).

### Evidence collection result

| Item | Result |
|---|---|
| FK expected / investigated | 55 / 55 |
| FK code evidence | 55/55 repository references; delete-domain proof incomplete |
| FK test evidence | 20/55 |
| FK data evidence | 55/55 |
| Evidence-complete | 0 |
| CASCADE / RESTRICT / SET NULL / SKIP | 0 / 0 / 0 / 0 |
| UNRESOLVED | 55 |
| Recommendation confidence | HIGH=0, MEDIUM=0, LOW=0, UNRESOLVED=55 |
| Critical fully investigated | 0/30 |
| Critical unresolved | 30 |
| Index evidence complete | 0/3 |
| Index recommendation | REQUIRED=0, REJECT=0, UNRESOLVED=3 |
| MariaDB compatibility | INCOMPLETE |
| MySQL compatibility | INCOMPLETE |
| Schema/data/migration changes | NO |
| Phase 4.4 | STILL OPEN |
| Phase 4.5 | NOT STARTED |
| Production Deployment Gate | CLOSED |

## Phase 4.4 — FK Semantic and Index Decision Completion

This is an evidence-audit continuation only. The phase remains open. No production connection, schema/data change, migration execution/creation, application-code change or deployment occurred.

### Scope and count assertions

The regenerated `output/phase44/fk-semantic-review.csv` contains 170 rows. Programmatic counts match the prior Phase 4.4 state: preliminary KEEP `115`, DECISION/CASCADE `55`, critical-risk `30`, high-risk `25`, medium-risk `115`. This task inspected only the 55 DECISION rows; the 115 preliminary KEEP rows were not confirmed.

### 55 FK evidence status

The 55 rows have structural evidence from the verified local clone: fresh action `CASCADE`, production constraint absent, and orphan result `0`. Migration source families were located read-only. However, a migration’s `onDelete('cascade')` declaration is not proof of application domain deletion behavior.

The required per-FK parent/child model mapping, exact `delete`/`forceDelete` entry points, observer/service/Filament cleanup trace, test expectation, cascade depth and per-FK row cardinality were not all established with file and line evidence. Therefore no row is confirmed as canonical `CASCADE`, `RESTRICT`, `SET NULL` or `SKIP`.

| Evidence result | Count |
|---|---:|
| DECISION FK expected | 55 |
| DECISION FK inspected | 55/55 |
| Evidence-complete FK | 0 |
| Unresolved FK | 55 |
| Technical recommendation CASCADE | 0 |
| Technical recommendation RESTRICT | 0 |
| Technical recommendation SET NULL | 0 |
| Technical recommendation SKIP | 0 |
| Technical recommendation UNRESOLVED | 55 |
| Critical-risk expected | 30 |
| Critical-risk evidence complete | 0/30 |
| Critical unresolved | 30 |

All 55 rows explicitly record `NO TEST EVIDENCE` or incomplete per-relation mapping where applicable; no absence of a test was used as a domain decision. Full row summary: [decision-fk-evidence.md](C:\Users\Codex\Desktop\yalova-yazilim-saas\output\phase44\decision-fk-evidence.md). The CSV now includes the required evidence fields and marks decision rows `decision_status=BLOCKED`, `evidence_recommendation=UNRESOLVED`; preliminary KEEP rows remain preliminary.

### Index evidence status

The three candidates were checked against the real-data upgraded local clone, not the empty fresh database. Candidate tables had row counts `0`, `111` and `0` respectively. The device identity candidate had combined identity cardinality `75` over `111` rows; its EXPLAIN was a low-volume full scan. The ecommerce and history candidates had no rows, so cardinality and write-cost conclusions are unavailable. Existing indexes and EXPLAIN outputs are recorded in [index-evidence.md](C:\Users\Codex\Desktop\yalova-yazilim-saas\output\phase44\index-evidence.md).

| Index result | Count |
|---|---:|
| Index candidates | 3 |
| Evidence completed | 0/3 |
| Technical REQUIRED | 0 |
| Technical REJECT | 0 |
| Technical UNRESOLVED | 3 |

The existing EXPLAIN plans are evidence only, not approval: optimizer choices are affected by low data volume and existing prefix indexes. No index was added, removed or changed.

### Compatibility and gate

MariaDB 10.4 compatibility evidence: **INCOMPLETE**. MySQL 8 compatibility evidence: **INCOMPLETE**. The candidate action set is unresolved, and no migration syntax was tested. Fresh canonical target remains **PARTIAL**. Schema repair remains **BLOCKED**.

### Evidence audit result

| Item | Result |
|---|---|
| CSV updated | YES |
| decision-fk-evidence.md | CREATED |
| index-evidence.md | CREATED |
| Schema changed | NO |
| Data changed | NO |
| Migration executed | NO |
| Migration created | NO |
| Production connection | NO |
| Deployment | NO |
| Overall | **EVIDENCE INCOMPLETE** |
| FAZ 4.4 status | **STILL OPEN** |
| FAZ 4.5 | **NOT STARTED** |
| Production Deployment Gate | **CLOSED** |

## Phase 4.4 — Canonical Schema Decision and Repair Design

This phase is design-only and read-only. No production server/database was contacted; no local clone schema was changed; no migration, FK, index or column was added, removed or rewritten.

### Canonical decision principle

The target is not an automatic copy of the fresh schema or the production schema. It is:

`current application contract + current domain contract + required data-integrity constraints`.

The Phase 4.3.1 structural result proves that all 170 relations are data-compatible today (`170/170` orphan checks, total orphan rows `0`). It does not by itself approve future parent-delete behavior.

### 170-FK semantic review result

The full row-level design inventory is at [fk-semantic-review.csv](C:\Users\Codex\Desktop\yalova-yazilim-saas\output\phase44\fk-semantic-review.csv). It contains the child/parent columns, fresh delete/update rules, root-cause family, risk, orphan result and preliminary decision for all 170 rows.

| Preliminary decision | Count | Meaning |
|---|---:|---|
| KEEP-PRELIMINARY | 115 | Fresh `SET NULL` or `RESTRICT`; still requires relation and delete-flow confirmation. |
| CHANGE confirmed | 0 | No delete action is changed without an explicit domain decision. |
| SKIP | 0 | No FK was proven unnecessary. |
| DECISION REQUIRED | 55 | Fresh `CASCADE`; parent deletion can remove tenant, accounting, stock-history, payment, authorization or technical-service data. |
| Total | 170 | Exact structural missing set. |

Risk review: **30 critical**, **25 high**, **115 medium**. Critical/high candidates include tenant `firma_id` cascades and cascades touching financial, invoice, stock-history, payment, audit or technical-service records. The application has soft-delete models and explicit firm cleanup flows; therefore a database hard-delete cascade must not be treated as equivalent to an application soft delete.

Domain/module review observations:

- Core/tenant/auth: tenant and authorization cascades require explicit hard-delete policy; soft delete is not enough evidence to approve physical cascades.
- Cari/accounting/finance: historical movements, settlements and account data should survive parent removal or be blocked; cascade candidates remain high/critical decisions.
- Invoice/stock/depot/measurement: invoice lines, stock movements and related history require retention and reverse/void semantics review.
- Technical service: service records, device history, logs and documents are historical records; cascades require explicit deletion policy.
- Ecommerce/payment: order history and payments require retention review; order-line cascades may be valid only for deliberate aggregate deletion.
- Personnel, restaurant and secretary: no missing FK was approved as an unconditional cascade without a verified hard-delete flow.

The appendix marks application relevance as `REQUIRED-GUARD`; exact Eloquent relation and controller/delete-flow confirmation remains a precondition for each preliminary KEEP or DECISION row. This is intentional: the repository contains many model relations and soft deletes, but no safe global rule that can infer domain deletion policy for every FK.

### CASCADE / SET NULL / RESTRICT policy

- `CASCADE` — not canonical by default for tenant, finance, invoice, stock-history, payment, audit or technical-service records. The 55 cascade rows are `DECISION REQUIRED` until a hard-delete contract is approved. Junction/pure aggregate cases may later be KEEP, but that is not assumed here.
- `SET NULL` — candidate for KEEP only where the child column is nullable and the application tolerates a missing historical parent. The parent relation and null behavior must be asserted in tests.
- `RESTRICT` — candidate for KEEP for historical references, provided the application delete flow expects the parent deletion to be blocked or uses soft delete. Hard-delete tests remain required.
- `ON UPDATE` — all reviewed signatures use `RESTRICT`; no update cascade is proposed.

### Active production-only columns

All three columns are canonical: **3/3**. They are actively referenced by current stock-card expiry and barcode sale/return flows, are physically present in upgraded production, absent in fresh, nullable with `NULL` default, and currently have zero non-null rows. A future additive migration must create them on fresh and no-op on existing production via `Schema::hasColumn`; its `down()` must not drop existing production data.

### Index design

The canonical required-index candidate count is **3** and index decision-required count is **3** pending final cardinality/query-plan review:

1. `ecommerce_pazaryeri_entegrasyonlari(firma_id, aktif_mi)` — active integration filtering; repository evidence includes tenant and `aktif_mi` predicates. Proposed action: ADD after `EXPLAIN`/cardinality check.
2. `teknik_servis_kayitli_cihazlar(firma_id, cihaz_id, marka_id, model_no)` — device identity lookup; repository/report flows use device and brand/model fields. Proposed action: ADD after column-order/cardinality check.
3. `teknik_servis_kayitli_cihaz_degisiklikleri(firma_id, kayitli_cihaz_id)` — tenant/device history lookup. Proposed action: evaluate after FK repair because FK-created support indexes can alter the final shape.

The two differently named technical-service equivalent pairs require no convergence repair. The two FK-implicit fresh-only rows must be re-evaluated after the FK batches; index repair must not run first.

### Repair batch design — no implementation yet

The following is the proposed implementation sequence. Every batch must fail fast and must not delete or rewrite data.

| Batch | Scope | Design preconditions | Risk / lock |
|---|---|---|---|
| REPAIR-01 | Core tenant/auth | Tables/columns exist, equivalent FK absent, per-FK orphan=0, semantic approval for cascades | Critical; firmalar/users and pivots |
| REPAIR-02 | Cari/accounting/finance | Same assertions plus historical-retention approval | Critical/high; financial tables |
| REPAIR-03 | Invoice/finance closure/payment | Same assertions plus invoice/audit delete-flow tests | Critical; accounting history |
| REPAIR-04 | Stock/depot/measurement | Same assertions plus stock movement retention tests | High; stock history |
| REPAIR-05 | Technical service | Same assertions plus service/device history tests | High; many service tables |
| REPAIR-06 | Ecommerce/order/payment | Same assertions plus order/payment retention tests | High |
| REPAIR-07 | Personnel | Same assertions plus employee-history delete policy | Medium/high |
| REPAIR-08 | Restaurant/secretary/other | Same assertions plus module-specific delete tests | Medium |
| REPAIR-09 | Canonical active columns and approved indexes | `hasColumn`, final FK/index shape, no destructive down path | Medium; additive/no-op |

Each FK batch must check: child and parent table, child and parent columns, no equivalent FK by canonical signature, zero orphan rows, compatible column definitions and approved delete/update action. Unexpected state must abort. Each migration must be idempotent and support both fresh install and production upgrade. Historical seven migration rows, AD state and `stok_takip_tipi` must remain untouched.

### Compatibility, lock and rollback design

The design is compatible in principle with MySQL 8 and MariaDB 10.4.32, but implementation compatibility is **BLOCKED** until the exact approved action set is fixed. Driver checks must explicitly accept both `mysql` and `mariadb`; no MySQL-only branch is allowed.

FK creation must be ordered from low-dependency/core tables toward dependent tables, with a maintenance window and per-batch metadata-lock monitoring. Actual production row counts and execution plans must be captured immediately before implementation; this phase does not estimate lock duration from stale data.

Rollback is non-destructive: drop only the exact FK/index created by that batch, never drop the three active columns or delete data. A failed batch must stop and preserve the database for investigation. `down()` for active columns is intentionally non-destructive/no-op on existing production data.

### Canonical target and GO/NO-GO

The canonical target manifest is [canonical-schema-target.md](C:\Users\Codex\Desktop\yalova-yazilim-saas\output\phase44\canonical-schema-target.md). Planned repair would make the fresh schema canonical **YES**, but the current fresh schema remains **PARTIAL** until implementation and fresh/upgrade convergence tests pass.

**SCHEMA REPAIR IMPLEMENTATION: BLOCKED** — 55 cascade semantics remain decision-required; 3 index candidates require final query/cardinality approval; exact per-relation application delete-flow assertions are not yet complete.

### Phase 4.4 result

| Field | Result |
|---|---|
| FK total reviewed | 170 |
| FK KEEP | 115 preliminary |
| FK CHANGE | 0 confirmed |
| FK SKIP | 0 |
| FK DECISION | 55 |
| High-risk FK | 55 high/critical combined |
| Critical-risk FK | 30 |
| Canonical required indexes | 3 candidates |
| Index decision required | 3 |
| Production-only columns canonical | 3/3 |
| Fresh canonical after planned repair | YES (target), current is PARTIAL |
| Repair batches | 9 |
| MySQL 8 compatibility | BLOCKED pending final semantics |
| MariaDB 10.4 compatibility | BLOCKED pending final semantics |
| Rollback design | PASS — non-destructive design |
| Canonical manifest | CREATED |
| Schema repair implementation | BLOCKED |
| Schema modified | NO |
| Migration created | NO |

**PRODUCTION DEPLOYMENT GATE: CLOSED**

## Phase 4.3.1 — Schema Convergence Verification Completion

This verification was read-only. The isolated MariaDB instance was restarted at `127.0.0.1:3307` using the existing `data-phase32-test-20260822` datadir. The main XAMPP `3306` datadir, production server and production database were not contacted. No migration or schema operation was executed.

### Independent DB-pair and migration-state verification

| Assertion | Result |
|---|---|
| Fresh DB | `yalovayazilimsaas_fresh_phase421_20260822` |
| Upgraded DB | `yalovayazilimsaas_prod_phase421_20260822` |
| Server | MariaDB `10.4.32`, `127.0.0.1:3307` |
| Fresh FK constraints | 452 |
| Upgraded FK constraints | 282 |
| Fresh current migrations | 294/294 |
| Upgraded current migrations | 294/294, plus 7 historical rows; 301 total rows |
| Upgraded AD state | `AD=1`, `ADET=0` |
| Upgraded stock tracking | `basit=214`, `parti=0`, `seri=0` |

The pair and its preconditions match Phase 4.2.1. No wrong or partial clone was used.

### Independent FK recalculation

`information_schema.TABLE_CONSTRAINTS`, `KEY_COLUMN_USAGE` and `REFERENTIAL_CONSTRAINTS` were queried directly. Canonical equality used child table/ordered child columns, parent table/ordered parent columns, `ON DELETE` and `ON UPDATE`; constraint names were ignored.

| Result | Count |
|---|---:|
| Fresh FK | 452 |
| Upgraded FK | 282 |
| Fresh-only canonical FK | 170 |
| Production-only canonical FK | 0 |
| Common/functionally equivalent FK | 282 |
| Previously reported missing | 170 |
| Independently recalculated missing | 170 |

**Reported 170 = recalculated 170: EXACT MATCH.** No diff-tool artifact or differently named equivalent FK was found.

### SHOW CREATE TABLE spot-checks

The following 15 tables were checked in both databases with `SHOW CREATE TABLE`; every check agreed with `information_schema`:

| Table | Fresh FK definitions | Upgraded FK definitions | Agreement |
|---|---:|---:|---|
| `firmalar` | 1 | 0 | YES |
| `kullanici_yetkileri` | 3 | 0 | YES |
| `cariler` | 3 | 3 | YES |
| `faturalar` | 4 | 4 | YES |
| `fatura_kalemleri` | 6 | 6 | YES |
| `finans_hareketleri` | 5 | 1 | YES |
| `stok_hareketleri` | 5 | 2 | YES |
| `muhasebe_depolar` | 1 | 1 | YES |
| `stok_olculeri` | 2 | 2 | YES |
| `teknik_servis_kayitli_cihazlar` | 6 | 6 | YES |
| `personeller` | 5 | 5 | YES |
| `restoran_adisyonlari` | 11 | 11 | YES |
| `ecommerce_pazaryeri_entegrasyonlari` | 0 | 0 | YES |
| `odemeler` | 1 | 0 | YES |
| `sekreter_gorevleri` | 5 | 5 | YES |

### Missing-FK source, orphan and group verification

The independently regenerated 170-signature set was source-mapped `170/170`: category C create-table historical gap `169/169`, category D MariaDB driver issue `1/1` (`2026_03_30_130000_create_muhasebe_doviz_kurlari_table.php`). Category totals remain A=0, B=0, C=169, D=1, E=0, F=0, G=0, H=0; sum `170`.

Each missing FK was checked against the upgraded clone using a null-safe child-to-parent orphan query. Composite-signature handling was included; all 170 were checkable.

| Orphan result | Count |
|---|---:|
| Missing FK checks completed | 170 |
| Total orphan rows | 0 |
| Safe-to-add by data check | 170 |
| Blocked by orphan data | 0 |
| Unknown / uncheckable | 0 |

Group A/B/C remains **170 / 0 / 0** and sums to 170. Group A is data-safe but is not automatically approved: `CASCADE`, `SET NULL` and `RESTRICT` semantics still require domain review.

### Independent index verification

`information_schema.STATISTICS` was compared using table, ordered columns, uniqueness and prefix. The original row-level result is reproduced: **14 fresh-only rows** and **10 production-only medium rows**. After grouping index definitions by functional shape, the result is **5 fresh-only shapes**, **6 production-only shapes**, and **876 common shapes**. The 14 fresh-only rows classify as 8 rows belonging to 3 required candidates, 4 rows in 2 equivalent-different-name pairs, and 2 FK-implicit rows; fresh redundant/artifact/unknown rows: 0. The 10 medium rows were individually reconciled as renamed/equivalent or legacy/implicit definitions; no unresolved artifact was treated as a convergence repair.

Required-index repository usage was confirmed for the three candidates: ecommerce active integration filtering (`firma_id`, `aktif_mi`), technical-service device identity lookup (`firma_id`, `cihaz_id`, `marka_id`, `model_no`) and technical-service tenant/device history lookup (`firma_id`, `kayitli_cihaz_id`). These correspond to repository `where`/tenant scopes and device identity lookups; no index was changed.

### Production-only column recheck

| Table | Column | Fresh | Production type | Nullable/default | Non-null rows | Classification |
|---|---|---|---|---|---:|---|
| `fatura_kalemleri` | `uretim_tarihi` | ABSENT | `date` | YES / NULL | 0 | Production-only but required; should exist in fresh |
| `fatura_kalemleri` | `son_kullanma_tarihi` | ABSENT | `date` | YES / NULL | 0 | Production-only but required; should exist in fresh |
| `muhasebe_barkodlu_satis_iade_kalemleri` | `seri_nolari` | ABSENT | `longtext` | YES / NULL | 0 | Production-only but required; should exist in fresh |

All three physical checks match Phase 4.3. Repository references remain active in stock-card expiry flows and barcode sale/return model/service/Livewire flows.

### Phase 4.3.1 final result

| Item | Result |
|---|---|
| Correct DB pair | PASS |
| FK count verification | PASS — 452 / 282 / 170 |
| SHOW CREATE TABLE spot checks | PASS — 15/15 |
| Source-mapped missing FK | PASS — 170/170 |
| Safe-to-add FK | PASS by orphan check — 170 |
| Orphan-blocked FK | 0 |
| Root-cause sum | PASS — 170 |
| Group A/B/C | PASS — 170 / 0 / 0 |
| Fresh-only index recalculation | PASS — 14 rows / 5 shapes |
| Medium index verification | PASS — 10/10 |
| Production-only columns | PASS — 3/3 |
| Fresh canonicality | PARTIAL |
| Schema repair readiness | BLOCKED — semantic FK and index decisions remain |
| Schema modified | NO |
| Migration executed | NO |

**OVERALL: PHASE 4.3 VERIFIED**

**PRODUCTION DEPLOYMENT GATE: CLOSED**
## Phase 4.4 — Autopilot Evidence Completion

Read-only controlled autopilot result. Stage A KEEP115 sanity passed: 115/115 audited, 115/115 source-mapped, 115/115 structurally addable, orphan-blocked 0, and exception set 0. Stage B was skipped.

Stage C global FK merge passed: 170/170 unique candidates, CASCADE 25, RESTRICT 81, SET NULL 64, SKIP 0, UNRESOLVED 0; source-mapped 170/170; structurally addable 170/170; orphan-blocked 0; HIGH/CRITICAL conflicts 0; MariaDB 10.4 and MySQL 8 static compatibility blockers 0.

Stage D index gate remains BLOCKED: the three verified index candidates are still UNRESOLVED (REQUIRED 0, REJECT 0, UNRESOLVED 3) because query-shape, cardinality and write-cost evidence is incomplete. Stages E–G were not executed. No schema, data, migration, application, or production operation was performed. Phase 4.4 remains STILL OPEN and the production deployment gate remains CLOSED.
