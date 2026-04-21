# CLAUDE.md — Project Rules for vogo-family-web

## VOGO — Stack Profile & Response Rules

### Stack (confirmed)
- **Hosting:** Hostinger (shared / managed WP). Fișierele de producție sunt pe Hostinger; cele locale sunt o oglindă parțială.
- **CMS:** WordPress
- **E-commerce:** WooCommerce
- **Theme:** WoodMart **child theme** → `woodmark-child/` (nu edita parent theme-ul)
- **Page builders:**
  - **Elementor** (majoritatea paginilor publice; URL-urile de editare conțin `action=elementor`)
  - **"The Essential Premium"** (unele post-uri / widget-uri)
- **Custom plugins locale:** `plugins/vogo-notification-plugin`, `plugins/vogo-personalization`, `plugins/vogo-social-login`, `plugins/video-conferencing-with-zoom-api`
- **Brand colors:** `#1A3D2B` (vogo green), `#a8e6b8` (accent)

### Ce ESTE în directorul local
- `woodmark-child/` — child theme: `functions.php`, `custom-functions.php`, `api-functions.php`, `seo.php`, `perf.php`, `audit.php`, `mobile.php`, `inc/`, `inc-vogo/`, `woocommerce/` (template overrides)
- `plugins/` — plugin-urile custom VOGO (cod sursă editabil)
- `web_pages/`, `checkout/`, `myaccount/`, `order/`, `newsletter/`, `emails/` — shortcode-uri și template-uri PHP custom integrate prin theme
- `custom-functions.php`, `login-shortcode.php`, `register-shortcode.php`, `admin-links.php`, `important-links.php`, `formatting.php`, `setup_section_users.php` — integrări la rădăcină
- `style.css`, `admin.css`, `css/`, `js/`, `img/` — assets
- `wp-config.php` — config local (nu-l commita)

### Ce NU este local (nu căuta aici)
- `wp-content/plugins/` — plugin-urile third-party (Elementor, Essential Premium, WooDmart parent, Yoast/RankMath etc.) NU sunt sincronizate local
- **Conținutul Elementor / The Essential** (texte, culori, widget settings) — e în **baza de date**, NU în fișiere PHP. Nu-l vei găsi cu `grep`.
- Media Library (uploads) — pe server

### REGULI DE RĂSPUNS — UI-first

#### 1. Întrebări "unde / cum schimb X" (text, culoare, font, layout, widget)
**Răspunde într-o singură propoziție cu calea UI.** Fără grep, fără explorare de cod, fără 3 opțiuni.

Exemple canonice:
- "unde schimb culoarea titlului" → `selectează widget → tab Style → Text Color → #HEX → Update`
- "cum ascund o categorie Woo" → `WooCommerce → Settings → Products → Display` / `Products → All → Bulk Edit → Catalog visibility: Hidden`
- "cum schimb textul hero" → `editezi pagina cu Elementor → click pe widget → tab Content`
- "cum modific meniul" → `Appearance → Menus`
- "cum schimb SEO title" → `Yoast/RankMath metabox la finalul paginii`

#### 2. Când intri în cod sursă
DOAR dacă întrebarea e despre:
- Hook-uri / filtre / acțiuni WordPress (`add_action`, `add_filter`)
- Shortcode-uri custom, REST API endpoints, WooCommerce hooks
- Template overrides în `woodmark-child/woocommerce/`
- Plugin-urile custom din `plugins/vogo-*`
- Funcții custom din `custom-functions.php`, `api-functions.php`, `inc/`, `inc-vogo/`
- Checkout / emails / payment / shipping logic custom
- Userul spune EXPLICIT "în cod", "în functions.php", "în plugin"

#### 3. Ordinea de căutare când intri în cod
1. `woodmark-child/functions.php` + `custom-functions.php`
2. `woodmark-child/inc/` și `woodmark-child/inc-vogo/`
3. `woodmark-child/woocommerce/` (template overrides)
4. `plugins/vogo-*` (custom plugin-uri)
5. Rădăcina proiectului (`web_pages/`, `checkout/`, etc.)

#### 4. CSS override (fallback, nu primar)
Calea de editare CSS: `Appearance → Customize → Additional CSS` sau `WooDmart → Theme Settings → Custom CSS`. Oferă asta DOAR dacă UI-ul nu expune setarea.

#### 5. Hostinger-specific
- **hPanel** → Hosting → Manage → secțiunile: Files (File Manager), Databases (phpMyAdmin), WordPress (LiteSpeed Cache, Security, Staging)
- **Cache clear:** WordPress section → LiteSpeed Cache → Purge All. Sau plugin LiteSpeed Cache din WP admin.
- **SSH / SFTP:** hPanel → Advanced → SSH Access (pe planuri Business+)
- **phpMyAdmin:** hPanel → Databases → phpMyAdmin (pentru queries directe pe DB)
- **PHP version / error logs:** hPanel → Advanced → PHP Configuration / Error Logs
- **Staging:** hPanel → WordPress → Staging (clone înainte de modificări majore)

### Format răspuns

- **Maxim 5 linii** pentru întrebări de tip "unde/cum". Fără tabele, fără headers, fără liste bogate.
- Dacă întrebarea e de cod: răspuns concis + fișier:linie → patch.
- NU cere screenshot-uri suplimentare dacă ai deja una clară. Dă răspunsul cel mai probabil, apoi: "dacă setarea nu apare, trimite screenshot cu tab-urile vizibile".
- NU face grep global în tot repo-ul pentru texte vizibile în pagini — acelea sunt în DB (Elementor/Essential/post_content).

### Anti-patterns (ce NU faci)
- NU faci grep în repo după text din Elementor — nu-l găsești, e în `wp_postmeta` / `_elementor_data`
- NU dai liste de 3-4 opțiuni când una singură e evident corectă
- NU folosești "verifică dacă", "poate fi", "probabil" — dă calea concretă, nu ipoteze
- NU modifici parent theme, `wp-config.php`, sau `wp-content/plugins/` third-party — fă-le prin child theme / custom snippet / plugin setting
- NU sugerezi `git` (vezi Git Rules — user gestionează manual)

---

## /vogo — MENIU RAPID
When user types `/vogo` followed by a number, execute the corresponding action immediately:
| # | Command | Action |
|---|---------|--------|
| 1 | Check clip image | Read `C:\sources\VOGO\vogo-contracts\.contracts\img1.png` and describe/act on it |
| 2 | Check mesaj de la Backend | `cat` (tail -50) `C:\sources\VOGO\vogo-contracts\.contracts\mesaje.txt` — show all unread messages from bottom |
| 3 | Trimite mesaj catre Backend | Append a new message block to `C:\sources\VOGO\vogo-contracts\.contracts\mesaje.txt` using format: separator + `Claude Mobile -> Backend` + date + TOPIC + message body + signature `Claude Mobile` + separator |
| 4 | Permisiuni complete | Read all of: API contracts from `C:\sources\VOGO\vogo-contracts\`, mesaje.txt (tail), CLAUDE.md, and summarize current session state (branch, pending changes, last task) |

Shorthand aliases: `cmb` = `/vogo 2`, `cci` = `/vogo 1`

## Backend Communication Protocol — MANDATORY

Când ai nevoie de informații pe care NU le poți deduce 100% din cod + contracte (structură DB, ce face un endpoint pe server, semantica unui câmp, SQL exact), **întreabă Backend-ul în scris**. Nu ghici. Nu presupune. Nu te hazarda.

### Canale (fiecare are fișier dedicat)

| Canal | Fișier | Folosit de / pentru |
|---|---|---|
| **Mobile ↔ Backend** | `C:\sources\VOGO\vogo-contracts\.contracts\mesaje.txt` | Claude Mobile (app Flutter) ↔ Backend (WordPress/Woo) |
| **Woo/Web ↔ Backend** | `C:\sources\VOGO\vogo-contracts\.contracts\mesaje-woo-backend.txt` | Claude Woo (site web vogo.family) ↔ Backend |

Când lucrezi pe site-ul web (vogo.family / woodmark-child / plugins VOGO custom) → scrii în `mesaje-woo-backend.txt`. Când lucrezi pe app mobile → scrii în `mesaje.txt`.

### Format STRICT (obligatoriu, copy din `mesaje.txt`)

```
================================================================================
<De la> -> <Destinatar>
YYYY-MM-DD
TOPIC: <scurt, factual, ce vrei să rezolvi>
================================================================================

Salut,

<Context: 2-4 rânduri — ce faci, de ce, ce ai deja confirmat.>
<Referințe la contracte / fișiere existente.>

INTREBARI:

  1. <întrebare concretă, factuală>
  2. <întrebare concretă, factuală>
  ...

<Opțional: ce urmează să implementezi după ce primești răspunsul.>

<semnătură>
================================================================================
```

- **Emițători valizi:** `Claude Mobile`, `Claude Woo`, `Claude Backend`
- **Separator:** 80 × `=`
- **Data:** absolută `YYYY-MM-DD` (nu relativă: evită "ieri", "săptămâna trecută")
- **Blocuri:** separate cu linie goală
- Semnătura finală = emițătorul fără sufix

### Reguli de conținut

1. **Întrebări concrete, nu deschise.** "Valoarea exactă stocată în DB?" DA. "Cum funcționează categoriile?" NU.
2. **Numerotate.** Backend-ul răspunde punct cu punct în aceeași ordine.
3. **Zero presupuneri.** Nu scrie "presupun că este X, corect?". Scrie "care este X?".
4. **Include context minim** (2-4 rânduri) — ce încerci să faci, ce ai deja confirmat, ce blochează.
5. **Referințe la fișiere/contracte** când există (ex: `rest/all-categories.php`, `vogo-contracts/catalog/category-list.md`).
6. **Cere SQL/cod exact** când ai nevoie să replici logică (nu reinventa — copiază).
7. **Acknowledge marker-e** (opțional, dar util în mesaje.txt): `[MOBILE READ: YYYY-MM-DD]`, `[MOBILE IMPLEMENTED: YYYY-MM-DD — scurt rezumat]` — pentru a închide un thread.

### Cand NU folosești fișierul
- Informația e deja în `vogo-contracts/*.md` → citește contractul întâi.
- Informația e în cod local (child theme, plugin custom) → grep + read.
- Userul îți răspunde direct în conversație → nu mai dublezi în fișier.

### Flow tipic
1. Citești contractul relevant din `vogo-contracts/<domain>/<endpoint>.md`.
2. Dacă mai ai nevoi neclare → append întrebări în fișierul corect (`mesaje.txt` sau `mesaje-woo-backend.txt`) folosind format strict.
3. Anunți userul: "Am trimis întrebări la backend în `<file>`. Aștept răspunsul înainte să implementez."
4. La răspuns → `/vogo 2` (tail mesaje.txt) sau `cat` pe `mesaje-woo-backend.txt` → procesezi → implementezi.

### Anti-patterns
- ❌ Scrii cod bazat pe "probabil backend-ul face X" când poți întreba în 30 sec.
- ❌ Amesteci canale (întrebare despre Woo în `mesaje.txt` sau invers).
- ❌ Întrebări vagi ("cum e structurat X?") — pierzi o iterație.
- ❌ Duplicezi întrebări la care ai deja răspunsul în contracte.

## Moduri de execuție — `direct` / `ask`
- **`direct`** (DEFAULT la start de sesiune) — analiză → implementare directă → raport. Zero întrebări blocante. Nu cere confirmare, nu prezintă plan, nu pune "Procedez?". Implementează la calitate maximă și raportează ce a făcut.
- **`ask`** — modul consultativ: prezintă analiza, expune planul, cere confirmare înainte de implementare. Folosit pentru task-uri cu risc mare sau când userul vrea control explicit.
- Userul comută oricând scriind `direct` sau `ask` (fără slash).

## Git Rules
- NEVER run git push — I handle all pushes manually from Android Studio
- NEVER run git commit — I handle all commits manually
- NEVER run git add — I handle all git operations myself
- Do not suggest or execute any git commands whatsoever

## Behavior Rules
- **FA INTOCMAI CE CERE USERUL.** Cand ti se cere fisierul X, lucrezi pe fisierul X — nu pe Y "care e similar". Cand ti se cer 3 puncte, faci EXACT cele 3 puncte — nu substitui cu altceva. Verifica INAINTE de raport: "Am facut EXACT ce s-a cerut?"
- Fix it professionally. Do not change anything else beyond what is asked.
- Do NOT make assumptions or invent scenarios. Work only with validated data and confirmed facts.
- If you are not sure about something — ASK before proceeding.
- Expected results: fix exactly what was requested, nothing more, nothing less.
- Process only ONE clear task per request.
- Do not restructure, rename, or refactor code that was not part of the request.
- Total AI response time per request must not exceed 1 minute.

## Structured Code Comments — AI Hints
When working on a file, ALWAYS scan for `@AI:` tags in comments and RESPECT them:
- `@AI:OWNER` — only the named function/class writes to this resource. Do NOT add another writer.
- `@AI:SOURCE` — the single source of truth for this data. Do NOT read from other sources.
- `@AI:RULE` — business rule that MUST NOT be violated. Follow exactly.
- `@AI:FLOW` — step order in a complex flow. Do NOT reorder or skip steps.
- `@AI:NOCHANGE` — code that must NOT be modified without explicit user confirmation.
- `@AI:DEPENDS` — critical dependency. If you modify this, update all dependents.
- `@AI:FORMAT` — data format/structure that must be preserved exactly.

When adding new code to critical sections, ADD appropriate `@AI:` tags to protect future changes.

## Code Quality Rules
- Generate only code that compiles without errors. ZERO erori de sintaxa — verifica fiecare Edit: `{` are `}`, `(` are `)`, block function ca parametru termina cu `},` nu `)`. Reciteste blocul modificat mental inainte de a raporta done.
- Check the current branch and project structure before making any changes.
- Add explanatory comments in ROMANIAN (until app is finished, then translate to English). Comments must be on ONE LINE — never split across 3-5 lines.
- SINGLE SOURCE OF TRUTH: each data point (username, token, email) has ONE storage location. Never duplicate across global + per-brand + local storage. If needed in 2 places → read from the primary source, never copy.
- CLARITY: one function/class/object for one purpose. Never two functions for the same purpose (no fallbacks, no duplicates). Never one function for two purposes. Use descriptive names that explain what it does.
- READABLE CODE > SHORT CODE: write code that is easy to read, not compact. Use if/else on separate lines without braces (for single-line bodies), NOT ternary operators. Efficiency comes from REUSING functions/objects — not from condensing logic into fewer lines. Each line = one clear action. If you need 3 seconds to understand a line — it's too condensed.
- ISOLATE PER-ENTITY DATA: Brand A must never see Brand B's data. Token, username, otp → stored per-brand in AppSecureStore.elements[] (BrandElement), never in global storage. Global storage = only the currently active session token.
- NO IMPLICIT FALLBACKS: never chain fallback attempts (if not X try Y, if not Y try Z). This creates ambiguity and breaks things. Read from ONE explicit source. Only add fallback if explicitly requested by the user.
- MINIMAL SCOPE & REGRESSION SAFETY: modify ONLY what is requested. Do not clean up adjacent code, do not add unrequested features, do not make unconfirmed changes that could break existing functionality. Code carefully — every change is a potential regression in existing working features. When in doubt, ASK before changing.
- LOG DECISIONS: add a print at every branching point (if/else with dynamic data). This helps debugging enormously — when something goes wrong, the logs show exactly which path was taken and why.
- READ BEFORE WRITE: always read the data model before displaying fields, read the API response before parsing, read existing code before modifying. Never assume structure — verify first.
- GREP BEFORE DONE — after ANY rename, move, or signature change, run grep BEFORE reporting done:
  - Renamed variable/method → `grep -rn "oldName" lib/ --include="*.dart"`
  - Moved/deleted file → `grep -rn "filename" lib/ --include="*.dart"`
  - Added required param → `grep -rn "ClassName(" lib/ --include="*.dart"`


## UI Rules
- Design must be cool, modern and professional — first impression must convince clients immediately
- Every screen must feel polished, intentional and premium
- UI/UX must follow current mobile design best practices (spacing, typography, hierarchy, contrast)
- Performance is critical — no unnecessary rebuilds, no heavy widgets in scroll views,
  use const constructors wherever possible
- Animations and transitions must be smooth and purposeful — not decorative noise
- Every visual change must look better than what was there before, not just different
- When in doubt about a design decision — propose 2-3 options with reasoning, let me decide
- Brand colors for reference: #1A3D2B (vogo green), #a8e6b8 (accent)

## When Something Is Unclear
- Stop and ask a specific question before writing any code
- Do not guess and proceed — always confirm first

## Image Clipboard
When the user pastes an image or refers to a clipboard screenshot, read it from:
`C:\sources\VOGO\vogo-contracts\.contracts\img1.png`
This is the shared image drop path used across all VOGO projects.

## Change Summary
After every response that modifies code, always append a change summary table + my request:
FLUTTER Request: <same text from request>
Implementation:
| File | +added | ~updated | -deleted |
|------|--------|----------|----------|
| path/to/file.dart | N lines | N lines | N lines |
Count only lines actually changed (not context lines). Include all modified files.

After the table, add a brief **Implementation Notes** section (3-6 bullet points) explaining:
- What problem was diagnosed / root cause identified
- How the solution was designed (the reasoning)
- What was implemented and why that approach was chosen

## API Contracts
Before implementing any backend integration, read the contract from:
`C:\sources\VOGO\vogo-contracts\`


## Others
1. Rezolva problemele de o maniera professionala. Nu fa schmbari nesolicitate fara sa intrebi inainte.
2.  Nu faci presupuneri si scenarii. te bazezi doar pe date validate si informatii certe. unde nu esti sigur - intrebi!
3.  rezultate asteptate: fixeaza exact lucrurile cerute, alte chesiuni sa nu faci modificari de capul tau fara sa intrebi - riscam sa stricam alte lucru. asigura backward compatibility
4. Generează doar cod fără erori de compilare.
5. Optimizeaza total de răspuns al AI pentru request.
6. trebuie sa adaugi comentarii explicative in limba engleza la fieare modificare / adaugare de cod sursa, la fiecare sectiune

## Task Execution Protocol — MANDATORY
Depends on active mode (`direct` or `ask`):

**MANDATORY PRE-EXECUTION CHECK (both modes):**
0. BEFORE writing any code, re-read the relevant CLAUDE.md rules and memory feedback
1. Verify: "Am I doing EXACTLY what was asked? Not a variant, not an equivalent — EXACTLY."
2. Verify: "Am I adding anything unsolicited? Fallbacks? Renames? Extra features?"
3. If a CLAUDE.md rule conflicts with the task — ASK before proceeding, do NOT decide alone

**`direct` mode (DEFAULT):**
1. READ and analyze the task
2. IMPLEMENT directly — no plan presentation, no confirmation wait
3. REPORT what was done (change summary)

**`ask` mode:**
1. READ and analyze the task
2. PRESENT a plan: what you will do, which files are affected, estimated time
3. WAIT for explicit user confirmation ("da", "proceed", "ok", etc.) before writing any code
4. Only after confirmation — implement

**QUALITY > SPEED:** Never shortcut execution to be faster. Every unsolicited decision is a potential bug. Implement exactly what was requested — nothing more, nothing less.

## Time Management Rules
- Before starting any task, estimate how long it will take
- If estimated time > 1 minute: STOP and present a plan first
- Show me: what you plan to do, how many files affected, estimated time
- Wait for my approval before proceeding. If you do not receive an answer from me in 1 minute - proceed automatically with default option
- If a task is already running for > 1 minute: stop, report what was done, ask how to continue
- For large refactors (10+ files): always propose alternatives first:
  1. Quick fix (under 1 min)
  2. Proper fix (longer, needs approval)
  3. Defer for later


