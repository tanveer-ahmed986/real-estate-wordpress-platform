# RAG (Retrieval-Augmented Generation) Patterns

Production patterns for building retrieval-augmented generation systems.

---

## RAG Architecture Tiers

### Naive RAG

```
Query → [Embed] → [Vector Search] → [Top-K Chunks] → [LLM + Context] → Answer
```

Simple but has limitations: no query optimization, no re-ranking, no validation.

### Advanced RAG

```
Query → [Query Transform] → [Hybrid Search] → [Re-Rank] → [LLM + Context] → [Validate] → Answer
```

### Modular RAG

```
Query → [Router] → [Search Strategy A/B/C] → [Fusion] → [Re-Rank] → [Generate] → [Validate]
                        ↓
              [Decompose] → [Sub-queries] → [Multi-source Retrieval] → [Merge]
```

---

## Document Processing Pipeline

### Chunking Strategies

```python
from dataclasses import dataclass

@dataclass
class ChunkConfig:
    strategy: str          # "fixed", "semantic", "recursive", "document"
    chunk_size: int = 512  # tokens
    chunk_overlap: int = 50
    separators: list[str] | None = None

# Recursive character text splitter (most common)
def recursive_chunk(text: str, config: ChunkConfig) -> list[str]:
    separators = config.separators or ["\n\n", "\n", ". ", " ", ""]
    chunks = []

    for sep in separators:
        if len(text) <= config.chunk_size:
            chunks.append(text)
            break
        parts = text.split(sep)
        current = ""
        for part in parts:
            if len(current) + len(part) > config.chunk_size:
                if current:
                    chunks.append(current.strip())
                current = part
            else:
                current += sep + part if current else part
        if current:
            chunks.append(current.strip())
        break

    return chunks

# Semantic chunking (embedding-based)
async def semantic_chunk(text: str, threshold: float = 0.5) -> list[str]:
    sentences = split_sentences(text)
    embeddings = await embed_batch(sentences)

    chunks = []
    current_chunk = [sentences[0]]

    for i in range(1, len(sentences)):
        similarity = cosine_similarity(embeddings[i-1], embeddings[i])
        if similarity < threshold:
            chunks.append(" ".join(current_chunk))
            current_chunk = [sentences[i]]
        else:
            current_chunk.append(sentences[i])

    if current_chunk:
        chunks.append(" ".join(current_chunk))
    return chunks
```

### Chunk Strategy Selection

| Content Type | Strategy | Chunk Size | Overlap |
|-------------|----------|------------|---------|
| Technical docs | Recursive by headers | 512-1024 | 50-100 |
| Legal documents | By section/clause | 1024-2048 | 100-200 |
| Code | By function/class | Varies | 0 |
| Conversations | By turn/topic | 256-512 | 25-50 |
| Mixed content | Semantic | 512 | 50 |

### Metadata Enrichment

```python
@dataclass
class EnrichedChunk:
    content: str
    metadata: dict  # source, page, section, date, author
    embedding: list[float]
    summary: str | None = None  # LLM-generated summary
    keywords: list[str] | None = None
    parent_id: str | None = None  # For hierarchical retrieval
    child_ids: list[str] | None = None

async def enrich_chunk(chunk: str, source_metadata: dict) -> EnrichedChunk:
    embedding = await embed(chunk)
    summary = await summarize(chunk) if len(chunk) > 200 else None
    keywords = await extract_keywords(chunk)

    return EnrichedChunk(
        content=chunk,
        metadata=source_metadata,
        embedding=embedding,
        summary=summary,
        keywords=keywords,
    )
```

---

## Retrieval Strategies

### Hybrid Search (Vector + Keyword)

```python
async def hybrid_search(
    query: str,
    vector_store,
    keyword_index,
    alpha: float = 0.7,  # Weight for vector vs keyword
    top_k: int = 10,
) -> list[dict]:
    # Vector search
    query_embedding = await embed(query)
    vector_results = await vector_store.search(query_embedding, top_k=top_k * 2)

    # Keyword search (BM25)
    keyword_results = await keyword_index.search(query, top_k=top_k * 2)

    # Reciprocal Rank Fusion
    fused = reciprocal_rank_fusion(
        [vector_results, keyword_results],
        weights=[alpha, 1 - alpha],
        k=60,
    )

    return fused[:top_k]

def reciprocal_rank_fusion(result_lists: list[list], weights: list[float], k: int = 60) -> list:
    scores = {}
    for results, weight in zip(result_lists, weights):
        for rank, doc in enumerate(results):
            doc_id = doc["id"]
            if doc_id not in scores:
                scores[doc_id] = {"doc": doc, "score": 0}
            scores[doc_id]["score"] += weight / (k + rank + 1)

    sorted_results = sorted(scores.values(), key=lambda x: x["score"], reverse=True)
    return [r["doc"] for r in sorted_results]
```

### Query Transformation

```python
async def transform_query(original_query: str) -> list[str]:
    """Generate multiple search queries from user's question."""
    response = await llm_call(
        prompt=f"""Generate 3 different search queries to find information for answering:
"{original_query}"

Each query should approach the question from a different angle.
Return as JSON array of strings.""",
        output_schema=list[str],
    )
    return [original_query] + response  # Include original

# HyDE: Hypothetical Document Embeddings
async def hyde_query(query: str) -> str:
    """Generate a hypothetical answer to use as search query."""
    hypothetical = await llm_call(
        prompt=f"Write a short passage that would answer: {query}",
    )
    return hypothetical
```

### Re-Ranking

```python
async def rerank_results(
    query: str,
    results: list[dict],
    top_k: int = 5,
) -> list[dict]:
    """Re-rank using cross-encoder or LLM."""
    scored = []
    for result in results:
        relevance = await score_relevance(query, result["content"])
        scored.append({**result, "relevance_score": relevance})

    scored.sort(key=lambda x: x["relevance_score"], reverse=True)
    return scored[:top_k]
```

---

## Generation Patterns

### Context-Aware Generation

```python
GENERATION_TEMPLATE = """Answer the question based ONLY on the provided context.
If the context doesn't contain enough information, say "I don't have enough information to answer that."

## Context
{context}

## Question
{question}

## Instructions
- Cite specific passages using [Source N] notation
- If information conflicts between sources, note the discrepancy
- Be precise and factual
- Do not make up information not in the context"""
```

### Citation Generation

```python
class CitedAnswer(BaseModel):
    answer: str
    citations: list[Citation]
    confidence: float
    gaps: list[str]  # What information was missing

class Citation(BaseModel):
    text: str          # Quoted text from source
    source_id: str     # Document identifier
    relevance: float   # How relevant this citation is
```

---

## Advanced RAG Patterns

### Parent-Child Retrieval

Search on smaller chunks, return larger parent chunks for context.

```python
# Index: Small chunks (sentences) for precise retrieval
# Return: Parent chunks (paragraphs/sections) for complete context

async def parent_child_retrieve(query: str, top_k: int = 5) -> list[str]:
    # Search small chunks
    child_results = await vector_store.search(await embed(query), top_k=top_k * 3)

    # Get unique parent chunks
    parent_ids = list(set(r["parent_id"] for r in child_results))
    parents = await fetch_chunks(parent_ids[:top_k])

    return parents
```

### Self-RAG (Self-Reflective RAG)

Agent decides when to retrieve and validates its own retrieval.

```python
async def self_rag(query: str) -> str:
    # Step 1: Decide if retrieval is needed
    needs_retrieval = await assess_retrieval_need(query)

    if needs_retrieval:
        # Step 2: Retrieve
        docs = await retrieve(query)

        # Step 3: Assess relevance of each doc
        relevant_docs = []
        for doc in docs:
            is_relevant = await assess_relevance(query, doc)
            if is_relevant:
                relevant_docs.append(doc)

        # Step 4: Generate with relevant context
        answer = await generate_with_context(query, relevant_docs)

        # Step 5: Self-check for hallucination
        is_grounded = await check_groundedness(answer, relevant_docs)
        if not is_grounded:
            answer = await regenerate_with_stricter_context(query, relevant_docs)
    else:
        answer = await generate_without_context(query)

    return answer
```

### Agentic RAG

Agent uses retrieval as a tool, deciding when and how to search.

```python
retrieval_tool = {
    "name": "search_knowledge_base",
    "description": "Search the knowledge base for relevant information",
    "input_schema": {
        "type": "object",
        "properties": {
            "query": {"type": "string"},
            "filters": {
                "type": "object",
                "properties": {
                    "source": {"type": "string"},
                    "date_range": {"type": "string"},
                    "category": {"type": "string"}
                }
            },
            "top_k": {"type": "integer", "default": 5}
        },
        "required": ["query"]
    }
}
# Agent decides when/how to search during ReAct loop
```

---

## Evaluation Metrics

| Metric | What It Measures | Target |
|--------|-----------------|--------|
| Context Relevance | Are retrieved docs relevant? | > 0.8 |
| Answer Faithfulness | Is answer grounded in context? | > 0.9 |
| Answer Relevance | Does answer address the question? | > 0.85 |
| Context Precision | Ranking quality of retrieved docs | > 0.75 |
| Context Recall | Coverage of needed information | > 0.8 |

---

## Anti-Patterns

| Anti-Pattern | Problem | Fix |
|-------------|---------|-----|
| No chunking strategy | Poor retrieval quality | Choose strategy per content type |
| Fixed chunk size for all content | Breaks semantic units | Use recursive/semantic chunking |
| No metadata | Can't filter or cite | Enrich chunks with source metadata |
| Vector-only search | Misses keyword matches | Hybrid search (vector + BM25) |
| No re-ranking | Irrelevant results in top-K | Add cross-encoder re-ranking |
| Stuffing all results | Token waste, noise | Re-rank and select top-K |
| No hallucination check | Unfaithful answers | Add groundedness validation |
| Embedding model mismatch | Poor retrieval | Match embed model to content domain |
