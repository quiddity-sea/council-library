# CLASSIFICATION AND SEMANTIC IMPROVEMENT UPDATE PLAN - FULL SYSTEM OVERHAUL

**Purpose:**  
Transform the Quiddity Lore Sea from a flat folder structure into a deeply categorized semantic archive. This update implements a rigorous subfolder taxonomy across all top-level domains to ensure that every document—from foundational mythos and technical specs to Merrill Leo's personal creative works and visual media—is stored in its logically correct location.

**Scope:**  
- **Global Taxonomy Update:** Complete replacement of `quiddity_folders.yaml` with a detailed subfolder hierarchy for all 8 main domains.
- **Physical Reorganization:** Bulk relocation of existing files from root directories into the new subfolder structure.
- **Database Synchronization:** Updating `quiddity_files.relative_path` to reflect the new physical locations.
- **Semantic Alignment:** Refining keyword mappings to ensure high-precision auto-classification for future ingests.
- **Out of Scope:** Visual media ingestion (binary processing/OCR) is excluded from this phase; this update only prepares the *folder structure* to receive such media.

---

## STEP‑BY‑STEP IMPLEMENTATION

### 1. Safeguard Current State - DONE
```bash
# Backup the YAML config
cp /foreverbox_data/council-library/php-api/config/quiddity_folders.yaml \
   /foreverbox_data/council-library/php-api/config/quiddity_folders.yaml.bak.$(date +%Y%m%d%H%M%S)

# Database snapshot of file paths for rollback
mysqldump -u zeon7_user -pF0reverb0x#2o26sql quiddity_commons quiddity_files > /tmp/quiddity_files_pre_overhaul.sql
```

### 2. Execute Global Taxonomy Update - DONE
Replace the entire content of `/foreverbox_data/council-library/php-api/config/quiddity_folders.yaml` with the comprehensive 8-domain structure. This includes detailed subfolders for:
- **01_TheForeverbox_Mythos** (Origin, Canon, History)
- **02_ReInvigor_Texts** (Contracts, Completed, Proposals, Legal)
- **03_TheInitiative_Audio** (Stems, Lyrics, Mixes, Scripts, Releases)
- **04_FromTheNoise_Archives** (Research, Editorial, Features, News, Reviews, Guides, Interviews, Completed/Substack, Completed/Blogs)
- **05_Agent_Profiles** (Individual Agent folders, Biographies, Profiles, SOULs)
- **06_QuiddityLtd_Dev_Specs** (API, Database, Infra, Vector-Embeddings, Documentation)
- **07_MerrillLeo_CreativeWorks** (Stories, Comics, Essays, Personal Music)
- **08_VisualMedia** (Art, Photography, Illustrations, Concept-Art)

### 3. Infrastructure Preparation (Disk Layer) - DONE
Pre-create the directory tree to ensure uniform permissions and avoid "directory not found" errors during the first bulk move.
```bash
# All directories are created under /foreverbox_data/Quiddity_Lore_Sea/
# e.g., mkdir -p /foreverbox_data/Quiddity_Lore_Sea/04_FromTheNoise_Archives/research
# [Full directory list is derived from the updated YAML]
```

### 4. Bulk Reclassification and Physical Migration - DONE
Trigger the `ingestion_worker.php` to perform a "Full Sea Refresh".

**Step 4a: Fix FolderRouter Classification Logic**

The current FolderRouter has three problems that cause classification failures:

1. **Keyword threshold too high**: The threshold is set to 2 (minimum 2 keyword matches in first 4096 characters). Fiction and creative works often have only 1 match in their opening text. Lower the threshold from 2 to 1 for subfolder-level classification.
2. **No filename-based classification**: A file named `THE_FIRST_TEACHER.md` should be classifiable by its title. Add a filename-based classification step that runs before keyword classification, checking the filename against folder keywords.
3. **Vector classification path is dead**: The `vectorClassify()` method queries a `quiddity_folder_centroids` table that does not exist. The router always falls back to keyword matching. Either implement the centroids table and generation script, or remove the dead code path and document that classification is keyword-only for now.

**Code change in `FolderRouter.php`:**

```php
// In keywordClassify(): change threshold from 2 to 1
return $bestScore < 1 ? '_review' : $best;

// Add new method: filenameClassify()
// Runs before keywordClassify, checks filename against folder keywords
private function filenameClassify(string $filename): ?string
{
    $filenameLower = strtolower($filename);
    $scores = [];
    foreach ($this->folders as $folder => $info) {
        $keywords = $info['keywords'] ?? [];
        $score = 0;
        foreach ($keywords as $kw) {
            if (str_contains($filenameLower, strtolower($kw))) {
                $score += 3; // filename match is weighted 3x higher than body text
            }
        }
        if ($score > 0) {
            $scores[$folder] = $score;
        }
    }
    if (empty($scores)) return null;
    arsort($scores);
    return array_key_first($scores);
}

// In classify(): add filename check before keyword fallback
public function classify(string $contentText, array $chunkEmbeddings = [], ?string $filename = null): string
{
    // 1. Try filename-based classification first
    if ($filename) {
        $result = $this->filenameClassify($filename);
        if ($result !== null) return $result;
    }
    // 2. Try vector classification (if centroids exist)
    if ($this->embedder->isAvailable()) {
        $docVec = $this->embedder->embedOne(substr($contentText, 0, 2000));
        if ($docVec) {
            $result = $this->vectorClassify($docVec);
            if ($result !== null) return $result;
        }
    }
    // 3. Fall back to keyword classification
    return $this->keywordClassify($contentText);
}
```

**Step 4b: Add Force Re-classify Flag to Ingestion Worker**

The worker only moves files flagged as `pending`. Files already marked `indexed` are skipped entirely, even if the taxonomy has changed. Add a `--reclassify` flag that resets all files to `pending` before processing.

```php
// In ingestion_worker.php, add option:
if (in_array('--reclassify', $argv)) {
    $pdo->exec("UPDATE quiddity_files SET indexing_status = 'pending'");
    echo "All files reset to pending for re-classification.\n";
}
```

**Step 4c: Run the Full Sea Refresh**
```bash
cd /foreverbox_data/council-library/php-api
export DB_PASS=F0reverb0x#2o26sql
php ../scripts/ingestion_worker.php --reclassify --once
```
**Action performed by worker:**
1. Reset all files to `pending` (via `--reclassify` flag).
2. Iterate through every file in `quiddity_files`.
3. Run `FolderRouter::classify()` with filename, vector, and keyword fallback.
4. Perform `rename($fullPath, $targetPath)` to move the file into the specific subfolder.
5. Update the `relative_path` in the database.

**Step 4d: Document Vector Centroids Status**

The `quiddity_folder_centroids` table and `generate_folder_centroids.py` script are specified in the Architecture Blueprint V3 (Section 3.2, endpoint `/v1/commons/folders/rebuild-centroids`) but are NOT implemented. This is a known gap. For now, classification relies on filename and keyword matching. The centroids feature should be tracked as a separate task in the Architecture Blueprint, not blocking this plan.

### 5. Validation and Audit - DONE
Confirm the migration was successful via three checks:
- **Disk Check:** `ls -R /foreverbox_data/Quiddity_Lore_Sea/` to ensure no files are lingering in the top-level root directories.
- **Database Check:** Query for files still located in root vs those in subfolders.
- **Sample Verification:** Verify specific files (e.g., *The Architecture of Silence*) have moved to `07_MerrillLeo_CreativeWorks/stories/`.

### 6. Final Semantic Verification - DONE
Run test searches to ensure the new structure hasn't impacted retrieval.
- Search for "Sovereign" $\rightarrow$ check if result is in `01_TheForeverbox_Mythos/...`
- Search for "Karaj school" $\rightarrow$ check if result is in `07_MerrillLeo_CreativeWorks/stories/...`
- Search for "API endpoint" $\rightarrow$ check if result is in `06_QuiddityLtd_Dev_Specs/api/...`

---

## FULL TAXONOMY BLUEPRINT

### [01] The Foreverbox Mythos
- **Root Keywords:** foreverbox, mythos, ur-myth, pillar, eden, merrill, progenitor, protocol, foundation, origin, history, lore, canon, sovereign, creator, zeon7
- **Sub-structure:**
  - `origin_story`: origin, beginning, genesis, creation, first
  - `canon_documents`: canon, official, authoritative, doctrine, creed
  - `historical_records`: history, timeline, chronicle, past, evolution, milestone

### [02] ReInvigor Texts
- **Root Keywords:** reinvigor, client, spec, requirements, brief, scope, contract, deliverable, council, library, sovereign, project, proposal, agreement, terms, conditions, SLA, deliverables, milestone, roadmap
- **Sub-structure:**
  - `active_contracts`: active, current, live, ongoing, contract, agreement
  - `completed_projects`: completed, finished, delivered, closed, archived
  - `proposals`: proposal, bid, tender, offer, quotation, quote
  - `terms_conditions`: terms, conditions, T&C, legal, compliance, regulation

### [03] The Initiative Audio
- **Root Keywords:** audio, music, stem, production, mix, lyric, video, script, release, initiative, song, track, beat, instrumental, vocal, arrangement, master, mastering
- **Sub-structure:**
  - `stems`: stem, track, channel, isolate, vocal, drums, bass, guitar, keys, synth
  - `lyrics`: lyric, lyrics, words, verse, chorus, bridge, hook
  - `mixes`: mix, mixing, master, mastered, final, final-mix, stereo, surround
  - `scripts`: script, screenplay, narration, voiceover, dialogue, lines
  - `releases`: release, launch, drop, single, album, EP, LP

### [04] From The Noise Archives
- **Root Keywords:** ftn, from the noise, editorial, article, research, handbook, publication, report, analysis, investigation, exposé, feature, column, essay, commentary, opinion, review
- **Sub-structure:**
  - `research`: research, study, analysis, investigation, report, data, statistics, survey, poll, findings
  - `editorial`: editorial, comment, opinion, viewpoint, perspective, take, standpoint
  - `features`: feature, longform, narrative, story, profile, portrait
  - `news`: news, breaking, update, bulletin, alert, notice, announcement
  - `reviews`: review, critique, assessment, evaluation, rating, score
  - `guides`: guide, handbook, manual, instructions, howto, tutorial, walkthrough
  - `interviews`: interview, Q&A, conversation, dialogue, talk, discussion
  - `completed/sub_stack_posts`: substack, published, post, newsletter
  - `completed/blogs`: blog, website, published, post

### [05] Agent Profiles
- **Root Keywords:** zeon7, leon, gemma, otec, agent, profile, biography, bio, CV, résumé, background, history, story, journey, path, evolution, development, growth, learning, experience
- **Sub-structure:**
  - `zeon7`: zeon7, z7, z-seven, core, curator, layer0
  - `leon`: leon, l3, l-three, producer, layer2
  - `gemma`: gemma, g3, g-three, analyst, layer1
  - `otec`: otec, o4, o-four, architect, layer3, root
  - `biographies`: biography, bio, life, story, history, background
  - `profiles`: profile, overview, summary, snapshot, outline
  - `souls`: soul, SOUL, essence, spirit, nature, character

### [06] Quiddity Ltd Dev Specs
- **Root Keywords:** blueprint, architecture, schema, api, database, sql, infrastructure, spec, technical, vector, embedding, model, algorithm, process, workflow, pipeline, system, design, plan, blueprint, schematic, diagram, flow, chart, graph, table, structure, framework, module, component, package, library, API, REST, GraphQL, webhook, endpoint, route, controller, service, middleware, microservice, container, docker, kubernetes, k8s, server, cloud, AWS, Azure, GCP, DevOps, CI, CD, pipeline, build, test, QA, staging, production
- **Sub-structure:**
  - `api`: API, REST, GraphQL, webhook, endpoint, route, controller, service, middleware
  - `database`: database, DB, schema, table, column, index, key, foreign, primary, relation, SQL, NoSQL, MongoDB, PostgreSQL, MySQL, SQLite
  - `infrastructure`: infrastructure, infra, server, cloud, AWS, Azure, GCP, DevOps, CI, CD, pipeline, build, test, staging, production, container, docker, kubernetes, k8s, VM, virtual, network, security, firewall, load, balancer
  - `vector_embeddings`: vector, embedding, embed, similarity, search, semantic, neural, network, model, transformer, BERT, sentence, word, encoding, dimension, distance, metric, cosine, dot, product
  - `documentation`: documentation, doc, guide, manual, howto, tutorial, walkthrough, reference, handbook, wiki, FAQ

### [07] Merrill Leo Creative Works
- **Root Keywords:** merrill, fiction, story, novel, short story, comic, creative writing, narrative, tale, fable, memoir, essay, personal, lyrics, song, music, composition
- **Sub-structure:**
  - `stories`: story, fiction, novel, short story, tale, fable, narrative, plot, character, setting, theme, genre, literary
  - `comics`: comic, graphic, novel, sequential, art, panel, strip, storyboard, illustration, drawing, ink, line, color
  - `essays`: essay, reflection, personal, memoir, nonfiction, commentary, opinion, viewpoint, perspective, reflection, critique, analysis
  - `music`: music, song, lyrics, melody, tune, composition, instrumental, vocal, words, verse, chorus, bridge, hook, produce, record

### [08] Visual Media
- **Root Keywords:** art, artwork, illustration, drawing, painting, sketch, photograph, photo, pic, img, visualize, diagram, chart, infograph, infographic, concept, concept-art, sprite, texture, material, render, rendering, CGI, 3D, 2D, pixel, voxel, animation, anim, motion, graphic, design, layout, typography, font, type, icon, logo, symbol, emblem, badge, sticker, poster, flyer, banner, billboard, ad, advertisement
- **Sub-structure:**
  - `art`: art, artwork, painting, drawing, sketch, illustration, fine, oil, acrylic, watercolor, pastel, charcoal, ink, pen, pencil, crayon, marker, paint, brush, canvas, paper
  - `photography`: photo, photograph, image, pic, img, camera, lens, exposure, aperture, shutter, ISO, film, digital, DSLR, mirrorless, phone, mobile, shot, capture, light, lighting, studio, outdoor, landscape, portrait, wildlife, street, documentary, fine, art
  - `illustrations`: illustration, diagram, chart, graph, infographic, visualize, explain, schematic, blueprint, plan, map, flowchart, org, chart, hierarchy, tree, network, wireframe, mockup, prototype, UI, UX, interface, screen, view
  - `concept-art`: concept, concept-art, idea, thought, vision, look, feel, mood, tone, atmosphere, environment, world, building, creature, character, design, costume, prop, set, scenery, background, foreground, midground

---

## NOTES ON EXECUTION
- **Additive Change**: This is a non-destructive structural upgrade.
- **Semantic Benefit**: By moving from "Flat Root" to "Deep Hierarchy", the `FolderRouter` can now provide higher-confidence classification and the user can navigate the physical filesystem as a logical map of their life's work.
- **Rollback**: Full rollback is possible via YAML restoration and database snapshot recovery.