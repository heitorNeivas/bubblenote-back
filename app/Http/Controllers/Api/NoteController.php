<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreNoteRequest;
use App\Http\Requests\UpdateNoteRequest;
use App\Http\Resources\NoteResource;
use App\Models\Note;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

/**
 * CRUD de Notas.
 *
 * Isolamento por usuario em duas camadas independentes:
 *  1. Route model binding com escopo de dono (routes/api.php) => {note}
 *     so resolve dentro das notas do usuario; id alheio/inexistente = 404.
 *  2. NotePolicy via $this->authorize() => defesa em profundidade e ponto
 *     unico para evoluir as regras (ex.: compartilhamento futuro).
 */
class NoteController extends Controller
{
    /**
     * GET /api/notes
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Note::class);

        $notes = $request->user()->notes()
            ->with(['tags', 'linkedNotes'])
            ->latest('updated_at')
            ->paginate($request->integer('per_page', 15));

        return NoteResource::collection($notes);
    }

    /**
     * POST /api/notes
     */
    public function store(StoreNoteRequest $request): JsonResponse
    {
        $this->authorize('create', Note::class);

        $note = DB::transaction(function () use ($request) {
            /** @var Note $note */
            $note = $request->user()->notes()->create($request->safe()->only([
                'title',
                'body_markdown',
                'properties',
            ]));

            $this->syncTags($request, $note, $request->input('tags'));
            $this->syncLinks($request, $note, $request->input('linked_note_ids'));

            return $note;
        });

        $note->load(['tags', 'linkedNotes']);

        return NoteResource::make($note)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/notes/{note}
     */
    public function show(Note $note): NoteResource
    {
        $this->authorize('view', $note);

        $note->load(['tags', 'linkedNotes', 'backlinks']);

        return NoteResource::make($note);
    }

    /**
     * PATCH/PUT /api/notes/{note}
     */
    public function update(UpdateNoteRequest $request, Note $note): NoteResource
    {
        $this->authorize('update', $note);

        DB::transaction(function () use ($request, $note) {
            $note->update($request->safe()->only([
                'title',
                'body_markdown',
                'properties',
            ]));

            if ($request->has('tags')) {
                $this->syncTags($request, $note, $request->input('tags'));
            }

            if ($request->has('linked_note_ids')) {
                $this->syncLinks($request, $note, $request->input('linked_note_ids'));
            }
        });

        $note->load(['tags', 'linkedNotes']);

        return NoteResource::make($note);
    }

    /**
     * DELETE /api/notes/{note}
     */
    public function destroy(Note $note): Response
    {
        $this->authorize('delete', $note);

        $note->delete();

        return response()->noContent();
    }

    /**
     * Converte nomes de tag em ids (criando as inexistentes NO VOCABULARIO
     * DO USUARIO) e sincroniza o pivo `note_tag`.
     *
     * @param  array<int, string>|null  $names
     */
    private function syncTags(Request $request, Note $note, ?array $names): void
    {
        if ($names === null) {
            return;
        }

        $ids = collect($names)
            ->map(fn (string $name) => trim($name))
            ->filter()
            ->unique()
            ->map(fn (string $name) => $request->user()->tags()->firstOrCreate(['name' => $name])->id)
            ->all();

        $note->tags()->sync($ids);
    }

    /**
     * Sincroniza os links de saida, aceitando apenas notas do proprio usuario.
     *
     * @param  array<int, int>|null  $targetIds
     */
    private function syncLinks(Request $request, Note $note, ?array $targetIds): void
    {
        if ($targetIds === null) {
            return;
        }

        $validTargets = $request->user()->notes()
            ->whereIn('id', $targetIds)
            ->where('id', '!=', $note->id)
            ->pluck('id')
            ->all();

        $note->linkedNotes()->sync($validTargets);
    }
}
