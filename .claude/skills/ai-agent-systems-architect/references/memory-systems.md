# Memory Systems for AI Agents

Patterns for implementing memory in agent systems: working memory, short-term, long-term, episodic, semantic, and entity memory.

---

## Memory Architecture Overview

```
┌─────────────────────────────────────────────┐
│                Agent Runtime                 │
│  ┌─────────────────────────────────────────┐│
│  │ Working Memory (context window)         ││
│  │ - Current messages, tool results        ││
│  │ - Active reasoning state                ││
│  └─────────────────────────────────────────┘│
│  ┌──────────────┐  ┌──────────────────────┐│
│  │ Short-Term   │  │ Long-Term Memory     ││
│  │ (session)    │  │ ┌──────────────────┐ ││
│  │ - Chat hist  │  │ │ Episodic         │ ││
│  │ - Task state │  │ │ (past actions)   │ ││
│  │ - Temp vars  │  │ ├──────────────────┤ ││
│  └──────────────┘  │ │ Semantic         │ ││
│                     │ │ (knowledge/facts)│ ││
│                     │ ├──────────────────┤ ││
│                     │ │ Entity           │ ││
│                     │ │ (user profiles)  │ ││
│                     │ └──────────────────┘ ││
│                     └──────────────────────┘│
└─────────────────────────────────────────────┘
```

---

## Memory Type 1: Working Memory

The LLM's context window. Most immediate but most limited.

### Management Strategies

| Strategy | How | When |
|----------|-----|------|
| **Full history** | Send all messages | Short conversations (<20 turns) |
| **Sliding window** | Keep last N messages | Medium conversations |
| **Summarization** | LLM summarizes older messages | Long conversations |
| **Token-aware truncation** | Drop oldest when near limit | Automated management |

```python
# LangGraph message management
from langgraph.graph import MessagesState
from langchain_core.messages import RemoveMessage

def manage_messages(state: MessagesState):
    messages = state["messages"]
    if len(messages) > 20:
        # Summarize older messages
        summary = llm.invoke(f"Summarize this conversation:\n{messages[:15]}")
        # Keep summary + recent messages
        delete_ids = [m.id for m in messages[:15]]
        return {
            "messages": [RemoveMessage(id=id) for id in delete_ids]
                + [SystemMessage(content=f"Previous context: {summary}")]
        }
    return {}
```

---

## Memory Type 2: Short-Term Memory

Persists within a session/task but cleared between sessions.

### Implementation Patterns

```python
# Redis-backed session memory
class SessionMemory:
    def __init__(self, session_id: str, redis_client, ttl: int = 3600):
        self.key = f"agent:session:{session_id}"
        self.redis = redis_client
        self.ttl = ttl

    async def store(self, key: str, value: any):
        await self.redis.hset(self.key, key, json.dumps(value))
        await self.redis.expire(self.key, self.ttl)

    async def recall(self, key: str) -> any:
        data = await self.redis.hget(self.key, key)
        return json.loads(data) if data else None

    async def get_context(self) -> dict:
        """Get all session context for injection into prompt."""
        data = await self.redis.hgetall(self.key)
        return {k: json.loads(v) for k, v in data.items()}

# OpenAI Agents SDK Sessions
from agents.sessions import SQLiteSession

session = SQLiteSession(database="agent_sessions.db")
agent = Agent(name="assistant", instructions="...", session=session)
# Session automatically persists working context across runs
```

### CrewAI Short-Term Memory

```python
from crewai import Crew

crew = Crew(
    agents=[agent1, agent2],
    tasks=[task1, task2],
    memory=True,  # Enables all memory types
    # Short-term: RAG over recent interactions within crew execution
)
```

---

## Memory Type 3: Long-Term Memory

Persists across sessions. The agent accumulates knowledge over time.

### Vector Store Pattern

```python
# Long-term memory with vector DB
from langchain_openai import OpenAIEmbeddings
from langchain_chroma import Chroma

class LongTermMemory:
    def __init__(self, collection_name: str):
        self.store = Chroma(
            collection_name=collection_name,
            embedding_function=OpenAIEmbeddings()
        )

    def remember(self, content: str, metadata: dict = None):
        """Store a memory with optional metadata."""
        self.store.add_texts(
            texts=[content],
            metadatas=[metadata or {}]
        )

    def recall(self, query: str, k: int = 5) -> list[str]:
        """Retrieve relevant memories."""
        docs = self.store.similarity_search(query, k=k)
        return [doc.page_content for doc in docs]

    def recall_with_score(self, query: str, k: int = 5, threshold: float = 0.7):
        """Retrieve memories above relevance threshold."""
        results = self.store.similarity_search_with_relevance_scores(query, k=k)
        return [(doc.page_content, score) for doc, score in results if score >= threshold]

# Usage in agent
memories = long_term.recall(user_query)
context = f"Relevant past knowledge:\n" + "\n".join(memories)
response = agent.invoke({"messages": [SystemMessage(content=context), user_msg]})
```

### SQL-Backed Long-Term Memory

```python
# For structured memories (facts, preferences, decisions)
class StructuredMemory:
    def __init__(self, db_url: str):
        self.engine = create_async_engine(db_url)

    async def store_fact(self, subject: str, predicate: str, value: str, confidence: float = 1.0):
        async with self.engine.begin() as conn:
            await conn.execute(
                text("INSERT INTO facts (subject, predicate, value, confidence, updated_at) "
                     "VALUES (:s, :p, :v, :c, NOW()) "
                     "ON CONFLICT (subject, predicate) DO UPDATE SET value=:v, confidence=:c, updated_at=NOW()"),
                {"s": subject, "p": predicate, "v": value, "c": confidence}
            )

    async def recall_about(self, subject: str) -> list[dict]:
        async with self.engine.connect() as conn:
            result = await conn.execute(
                text("SELECT predicate, value, confidence FROM facts WHERE subject = :s ORDER BY confidence DESC"),
                {"s": subject}
            )
            return [dict(row._mapping) for row in result]
```

---

## Memory Type 4: Episodic Memory

Stores past agent experiences (task attempts, successes, failures) for learning.

```python
class EpisodicMemory:
    def __init__(self, vector_store):
        self.store = vector_store

    def record_episode(self, task: str, actions: list[str], outcome: str, success: bool):
        """Record a complete task episode."""
        episode = {
            "task": task,
            "actions": actions,
            "outcome": outcome,
            "success": success,
            "timestamp": datetime.utcnow().isoformat()
        }
        # Store as searchable text
        text = f"Task: {task}\nActions: {'; '.join(actions)}\nOutcome: {outcome}\nSuccess: {success}"
        self.store.add_texts(texts=[text], metadatas=[episode])

    def recall_similar_tasks(self, current_task: str, k: int = 3) -> list[dict]:
        """Find similar past tasks to inform current approach."""
        docs = self.store.similarity_search(current_task, k=k)
        return [doc.metadata for doc in docs]

    def get_successful_strategies(self, task: str, k: int = 3) -> list[str]:
        """Find strategies that worked for similar tasks."""
        docs = self.store.similarity_search(
            task, k=k * 2,
            filter={"success": True}
        )
        return [doc.page_content for doc in docs[:k]]
```

### Using Episodic Memory in Agent Loop

```python
def agent_with_episodic_memory(task: str, episodic: EpisodicMemory):
    # Recall similar past tasks
    past_episodes = episodic.recall_similar_tasks(task)

    # Build context from past experience
    experience = ""
    for ep in past_episodes:
        status = "succeeded" if ep["success"] else "failed"
        experience += f"- Similar task {status}: {ep['task']} → {ep['outcome']}\n"

    # Inject into agent prompt
    system = f"""You are an AI agent. Learn from past experiences:
{experience}
Apply lessons learned to the current task."""

    # Execute task
    result = agent.invoke(task, system_prompt=system)

    # Record this episode
    episodic.record_episode(task, result.actions, result.output, result.success)
    return result
```

---

## Memory Type 5: Semantic Memory

Domain knowledge, facts, and relationships. Like a knowledge graph.

```python
# Graph-based semantic memory
class SemanticMemory:
    def __init__(self, graph_store):
        self.graph = graph_store  # Neo4j, NetworkX, etc.

    def add_knowledge(self, subject: str, relation: str, obj: str):
        """Add a fact: (subject) -[relation]-> (object)"""
        self.graph.add_edge(subject, obj, relation=relation)

    def query_knowledge(self, entity: str, relation: str = None) -> list[dict]:
        """Query facts about an entity."""
        if relation:
            return self.graph.get_edges(entity, relation=relation)
        return self.graph.get_all_edges(entity)

    def find_path(self, entity_a: str, entity_b: str) -> list:
        """Find relationship path between two entities."""
        return self.graph.shortest_path(entity_a, entity_b)
```

---

## Memory Type 6: Entity Memory

Tracks specific entities (users, accounts, projects) the agent interacts with.

```python
# CrewAI-style entity memory
class EntityMemory:
    def __init__(self, store):
        self.store = store  # Key-value or document store

    def update_entity(self, entity_type: str, entity_id: str, attributes: dict):
        """Update known attributes of an entity."""
        key = f"{entity_type}:{entity_id}"
        existing = self.store.get(key) or {}
        existing.update(attributes)
        existing["last_updated"] = datetime.utcnow().isoformat()
        self.store.set(key, existing)

    def get_entity(self, entity_type: str, entity_id: str) -> dict:
        return self.store.get(f"{entity_type}:{entity_id}") or {}

    def get_entity_context(self, entity_type: str, entity_id: str) -> str:
        """Get entity context string for prompt injection."""
        entity = self.get_entity(entity_type, entity_id)
        if not entity:
            return ""
        lines = [f"Known information about {entity_type} {entity_id}:"]
        for k, v in entity.items():
            if k != "last_updated":
                lines.append(f"  - {k}: {v}")
        return "\n".join(lines)
```

---

## LangGraph Checkpointing (State Persistence)

```python
from langgraph.checkpoint.sqlite import SqliteSaver
from langgraph.checkpoint.postgres import PostgresSaver

# SQLite for development
checkpointer = SqliteSaver.from_conn_string("checkpoints.db")

# Postgres for production
checkpointer = PostgresSaver.from_conn_string(DATABASE_URL)

graph = builder.compile(checkpointer=checkpointer)

# Resume from checkpoint
config = {"configurable": {"thread_id": "user-123"}}
result = graph.invoke(input, config=config)

# Time-travel: replay from any past state
states = graph.get_state_history(config)
past_state = list(states)[5]  # Get state from 5 steps ago
graph.invoke(None, config={**config, "checkpoint_id": past_state.config["checkpoint_id"]})
```

---

## Memory Selection Guide

| Scenario | Memory Types | Storage |
|----------|-------------|---------|
| Simple chatbot | Working + short-term | In-memory |
| Customer support | Working + short-term + entity | Redis + SQL |
| Research agent | Working + long-term + semantic | Vector DB + graph |
| Learning agent | All types | Vector DB + SQL + graph |
| Stateless worker | Working only | None (stateless) |

## Memory Anti-Patterns

| Anti-Pattern | Problem | Solution |
|-------------|---------|----------|
| Storing everything | Context overflow, noise | Store summaries, filter relevance |
| No TTL on memories | Stale data accumulates | Set expiry, periodic cleanup |
| No relevance filtering | Irrelevant context injected | Use similarity threshold |
| Mixing memory types | Hard to maintain | Separate stores per type |
| No memory versioning | Can't track changes | Timestamp + version all entries |
