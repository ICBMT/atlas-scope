# AtlasScope

Scan a Laravel, C++, C# or Python project and explore it as a 3D map: routes,
controllers, models, classes, namespaces, build targets and database tables —
with the relationships between them drawn as actual links, and every request's
journey traced end to end.

```
composer require atlas/scope
php artisan migrate
```

Then open **`/atlas`**. That is the whole installation.

---

## What it does

| | |
|---|---|
| **Scanner** | Four language profiles — Laravel/PHP, C++, C#, Python — behind one pipeline. A scan walks the source, builds a graph of nodes and edges, computes layers, layouts and insights, and stores all of it. |
| **3D atlas** | The graph rendered with three.js: one instanced mesh for nodes, one buffer for relationships, layered decks for architecture, and three layout engines (architecture layers, modules, spiral). Quality tiers from *High (bloom)* down to *Performance*, with an automatic step-down if the frame rate drops. |
| **Report page** | The same scan as a readable document: composition, metrics, per-language vocabulary, database tables with their columns. |
| **Request journeys** | Pick a route and the atlas lights the exact chain it walks — entry point → middleware → controller → services → models → tables. Compiled projects get their entry points (`main()`, an executable target) instead. |
| **Source viewer** | Every node opens the file it came from, in place, with the line it was declared on. |
| **Local assistant** | An optional chat panel that answers questions about the loaded project — running on *your* machine, free, with no API key. See [The local assistant](#the-local-assistant). |

### Uploads up to 150 MB, on a stock PHP install

PHP's `upload_max_filesize` is 2 MB out of the box, and that is where most
"upload failed" reports come from. Large archives are streamed here in ~1 MB
pieces as raw request bodies — which PHP does not treat as form uploads, so
neither `upload_max_filesize` nor `post_max_size` ever applies. The UI reads the
server's real capacity live and tells you which limit is binding, in numbers.

---

## Requirements

| | |
|---|---|
| PHP | 8.3+ with `ext-zip` and `ext-mbstring` |
| Laravel | 13.x |
| Database | SQLite by default — the package ships migrations and nothing else |
| Node | none. The renderer ships compiled in `dist/`; Node is only needed to rebuild it. |
| Queue | optional — see [Running a scan](#running-a-scan) |

## Installation

```bash
composer require atlas/scope
php artisan migrate
```

The service provider is discovered automatically. Migrations create five
`atlas_*` tables; nothing else in your application is touched.

By default AtlasScope is served under **`/atlas`**, so it can never shadow your
own routes. To serve it from the root of a dedicated installation:

```dotenv
ATLAS_ROUTE_PREFIX=
```

### Publish what you want to own

Nothing below is required to run the package — publishing is for hosts that want
a copy they can edit.

```bash
php artisan vendor:publish --tag=atlas-config    # config/atlas.php
php artisan vendor:publish --tag=atlas-assets    # dist/ → public/vendor/atlas
php artisan vendor:publish --tag=atlas-errors    # error pages → resources/views/errors
php artisan vendor:publish --tag=atlas-bin       # bin/limits.env, the dev-server upload limits
php artisan vendor:publish --tag=atlas-sources   # the unbuilt CSS/JS, to rebuild yourself
```

Assets are streamed by a route until you publish them, so the tool works on a
read-only `public/` directory, behind a proxy, or on a first run with no setup
step. Publishing moves 675 kB of static files to the web server where they
belong.

---

## Running a scan

Uploads and re-scans run on a queue so the request returns immediately, *unless*
your queue connection is `sync` — with the default driver a scan runs inline and
there is nothing to supervise.

```bash
php artisan queue:work --queue=atlas    # if QUEUE_CONNECTION is not 'sync'
```

From the command line:

```bash
php artisan atlas:scan /path/to/project --name="My project"   # scan a directory
php artisan atlas:scan --all                                  # re-scan everything
php artisan atlas:prune                                       # drop workspaces with no project row
php artisan atlas:serve --port=8080                           # artisan serve, with real upload limits
```

`atlas:serve` is worth knowing about: plain `php artisan serve` hands requests to
a *child* `php -S` process that knows nothing about your `-d` flags, which is why
uploads fail at 2 MB on a dev machine. `atlas:serve` puts the limits on the child
invocation itself, taken from `bin/limits.env` (publish it with `--tag=atlas-bin`).

---

## The local assistant

Optional, and entirely local: [Ollama](https://ollama.com) on your own machine,
no account, no key, no metering. With nothing listening the panel says so and
explains how to start one — it never falls back to a cloud service.

```bash
ollama pull qwen3:8b     # about 5 GB, the balance of speed and quality on a laptop
ollama serve
```

The panel knows the graph and reads file excerpts through a retriever, so it can
answer *what does this application do* and *what is this particular file doing*
with citations that open the node they came from. The model list and everything
else lives in `config/atlas.php` (`atlas.ai`), overridable from `.env`:

```dotenv
ATLAS_AI_BASE_URL=http://127.0.0.1:11434    # any OpenAI-compatible local runtime
ATLAS_AI_MODEL=qwen3:8b
```

---

## Configuration

Everything has a working default; see `config/atlas.php`.

| key | default | what it does |
|---|---|---|
| `atlas.routes.prefix` | `atlas` | URL prefix for every route. `''` serves it from the root. |
| `atlas.routes.middleware` | `['web']` | Put the tool behind `auth` here. |
| `atlas.storage_path` | `storage/app/atlas` | Where project workspaces are unpacked. Absolute path. |
| `atlas.max_archive_bytes` | 150 MB | Ceiling per uploaded archive. |
| `atlas.max_extracted_bytes` | 2 GB | Zip-bomb guard while unpacking. |
| `atlas.sync_scans` | = queue is `sync` | Run scans inline instead of dispatching them. |
| `atlas.scan_memory_limit` | `1024M` | Raised for console processes only — scans read whole trees. |
| `atlas.demo_enabled` | `true` | Offers the bundled sample project on the landing page. |
| `atlas.ai.*` | see above | The assistant: runtime, model, timeout, retrieval budget. |

---

## Rebuilding the renderer

Only needed if you change the JavaScript. The package's build is self-contained:
it emits `dist/atlas.js`, `dist/atlas.css` and a three.js chunk, plus a
`dist/version` file the layout uses to cache-bust.

```bash
npm install
npm run build
```

`--tag=atlas-sources` publishes the unbuilt sources into
`resources/vendor/atlas/` if you would rather fold them into your own build.

## Tests

```bash
composer install
composer test
```

The suite runs against a real Laravel application booted by Orchestra Testbench:
it uploads and scans fixtures in all four languages, walks the API, reads source
back out of a scanned project and drives the assistant against a fake runtime.

## How it is put together

`src/` holds the package; see [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) for
the pipeline, the language profiles, the graph payload, the renderer and the
focus engine.

| | |
|---|---|
| `src/Services/Scan/` | the pipeline, the four language profiles, the parsers and classifiers |
| `src/Services/Ai/` | the local model client, the project brief and the retriever |
| `src/Http/Controllers/` | the pages, the JSON API and the chunked upload endpoints |
| `src/Support/` | graph builder, path sandbox, workspace paths, asset URLs, PHP ini readings |
| `resources/js/atlas/` | the renderer: scene, layouts, store, inspector, tracer, assistant panel |
| `resources/demo/taskflow/` | the sample project offered on the landing page |

## Notes

- **Table names are prefixed** (`atlas_projects`, `atlas_scans`, …) so the package
  can be installed into an application that already has a `projects` table.
- **Error pages** are published, not registered: Laravel resolves
  `resources/views/errors` by convention, which a package cannot namespace.
- **Memory**: a scan of a very large project wants the CLI's `memory_limit`; the
  package raises it to `atlas.scan_memory_limit` in console processes only.
- **Licence**: MIT is declared in `composer.json`. Add your own `LICENSE` file
  before publishing if that is not the licence you intend.
