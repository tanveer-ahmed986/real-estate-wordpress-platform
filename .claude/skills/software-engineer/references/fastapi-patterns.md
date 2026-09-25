# FastAPI Patterns

## Project Structure (Production)

```
app/
├── main.py                    # FastAPI app, lifespan, root router
├── config.py                  # Settings via pydantic-settings
├── database.py                # Engine, session dependency
├── modules/
│   ├── auth/
│   │   ├── __init__.py
│   │   ├── router.py          # Auth endpoints (/token, /register)
│   │   ├── service.py         # Auth logic (hash, verify, JWT)
│   │   ├── dependencies.py    # get_current_user, get_current_active_user
│   │   ├── schemas.py         # Token, TokenData, UserCreate, UserPublic
│   │   └── models.py          # User SQLModel table
│   ├── items/
│   │   ├── __init__.py
│   │   ├── router.py          # CRUD endpoints
│   │   ├── service.py         # Business logic
│   │   ├── schemas.py         # ItemCreate, ItemUpdate, ItemPublic
│   │   └── models.py          # Item SQLModel table
│   └── __init__.py
├── shared/
│   ├── dependencies.py        # Shared deps (pagination, session)
│   ├── exceptions.py          # Custom exception handlers
│   ├── middleware.py           # Custom middleware
│   └── utils.py
├── migrations/                # Alembic migrations
│   ├── versions/
│   ├── env.py
│   └── alembic.ini
└── tests/
    ├── conftest.py            # Fixtures (test client, test DB)
    ├── test_auth.py
    └── test_items.py
```

---

## Configuration (pydantic-settings)

```python
from pydantic_settings import BaseSettings, SettingsConfigDict

class Settings(BaseSettings):
    model_config = SettingsConfigDict(
        env_file=".env",
        env_file_encoding="utf-8",
        case_sensitive=False,
    )

    # Application
    app_name: str = "My API"
    debug: bool = False

    # Database
    database_url: str = "sqlite:///./app.db"

    # Auth
    secret_key: str
    algorithm: str = "HS256"
    access_token_expire_minutes: int = 30

    # CORS
    allowed_origins: list[str] = ["http://localhost:3000"]


settings = Settings()
```

---

## Application Entry Point

```python
from contextlib import asynccontextmanager
from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from .config import settings
from .database import create_db_and_tables
from .modules.auth.router import router as auth_router
from .modules.items.router import router as items_router


@asynccontextmanager
async def lifespan(app: FastAPI):
    # Startup: create tables, load resources
    create_db_and_tables()
    yield
    # Shutdown: cleanup resources


app = FastAPI(
    title=settings.app_name,
    lifespan=lifespan,
    docs_url="/docs" if settings.debug else None,  # Disable docs in production
    redoc_url="/redoc" if settings.debug else None,
)

# CORS
app.add_middleware(
    CORSMiddleware,
    allow_origins=settings.allowed_origins,
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Routers
app.include_router(auth_router, prefix="/api/v1/auth", tags=["auth"])
app.include_router(items_router, prefix="/api/v1/items", tags=["items"])

# Health check
@app.get("/health")
async def health():
    return {"status": "ok"}
```

---

## Database Setup (SQLModel)

```python
from sqlmodel import SQLModel, Session, create_engine
from typing import Annotated
from fastapi import Depends
from .config import settings

# Engine
connect_args = {}
if settings.database_url.startswith("sqlite"):
    connect_args = {"check_same_thread": False}

engine = create_engine(
    settings.database_url,
    echo=settings.debug,
    connect_args=connect_args,
)

def create_db_and_tables():
    SQLModel.metadata.create_all(engine)

# Session dependency
def get_session():
    with Session(engine) as session:
        yield session

SessionDep = Annotated[Session, Depends(get_session)]
```

### Async SQLAlchemy (High-Performance)

```python
from sqlalchemy.ext.asyncio import create_async_engine, AsyncSession, async_sessionmaker

async_engine = create_async_engine(
    "postgresql+asyncpg://user:pass@localhost/dbname",
    echo=settings.debug,
    pool_size=20,
    max_overflow=10,
)

async_session = async_sessionmaker(async_engine, class_=AsyncSession, expire_on_commit=False)

async def get_async_session():
    async with async_session() as session:
        yield session

AsyncSessionDep = Annotated[AsyncSession, Depends(get_async_session)]
```

---

## Model Patterns (SQLModel Multi-Model)

```python
from sqlmodel import Field, SQLModel
from datetime import datetime

# ── Base model (shared fields, NOT a table) ──
class ItemBase(SQLModel):
    name: str = Field(index=True, min_length=1, max_length=100)
    description: str | None = Field(default=None, max_length=500)
    price: float = Field(gt=0)
    is_active: bool = Field(default=True)

# ── Database table model ──
class Item(ItemBase, table=True):
    id: int | None = Field(default=None, primary_key=True)
    owner_id: int = Field(foreign_key="user.id", index=True)
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)

# ── API input: create (client sends this) ──
class ItemCreate(ItemBase):
    pass  # Inherits name, description, price, is_active

# ── API input: update (all fields optional) ──
class ItemUpdate(SQLModel):
    name: str | None = None
    description: str | None = None
    price: float | None = Field(default=None, gt=0)
    is_active: bool | None = None

# ── API output: public (never expose internal fields) ──
class ItemPublic(ItemBase):
    id: int
    owner_id: int
    created_at: datetime
```

### Why Multi-Model?

| Model | Purpose | Prevents |
|-------|---------|----------|
| `ItemBase` | Shared field definitions | Code duplication |
| `Item` | Database table with internal fields | — |
| `ItemCreate` | API input for creation | Client setting `id`, `created_at` |
| `ItemUpdate` | Partial updates (all optional) | Requiring all fields on PATCH |
| `ItemPublic` | API output | Exposing `hashed_password`, internal IDs |

---

## Dependency Injection Patterns

### Reusable Type Aliases

```python
from typing import Annotated
from fastapi import Depends, Query

# Database session
SessionDep = Annotated[Session, Depends(get_session)]

# Current authenticated user
CurrentUser = Annotated[User, Depends(get_current_active_user)]

# Common pagination
class PaginationParams:
    def __init__(
        self,
        offset: int = Query(0, ge=0),
        limit: int = Query(20, ge=1, le=100),
    ):
        self.offset = offset
        self.limit = limit

PaginationDep = Annotated[PaginationParams, Depends()]
```

### Using in Routes

```python
@router.get("/", response_model=list[ItemPublic])
async def list_items(
    session: SessionDep,
    user: CurrentUser,
    pagination: PaginationDep,
):
    items = session.exec(
        select(Item)
        .where(Item.owner_id == user.id)
        .offset(pagination.offset)
        .limit(pagination.limit)
    ).all()
    return items
```

### Dependencies with Yield (Resource Cleanup)

```python
# Database session (auto-closes)
def get_session():
    with Session(engine) as session:
        yield session

# File handle (auto-closes)
async def get_temp_file():
    file = tempfile.NamedTemporaryFile(delete=False)
    try:
        yield file
    finally:
        os.unlink(file.name)

# External API client
async def get_http_client():
    async with httpx.AsyncClient() as client:
        yield client
```

### Global Dependencies

```python
# Apply to all routes in the app
app = FastAPI(dependencies=[Depends(verify_api_key)])

# Apply to all routes in a router
router = APIRouter(dependencies=[Depends(get_current_active_user)])
```

---

## Authentication (OAuth2 + JWT)

### Auth Service

```python
from datetime import datetime, timedelta, timezone
from typing import Annotated
import jwt
from fastapi import Depends, HTTPException, status
from fastapi.security import OAuth2PasswordBearer
from pwdlib import PasswordHash
from .config import settings
from .schemas import TokenData

password_hash = PasswordHash.recommended()  # Argon2id
oauth2_scheme = OAuth2PasswordBearer(tokenUrl="/api/v1/auth/token")

def hash_password(password: str) -> str:
    return password_hash.hash(password)

def verify_password(plain: str, hashed: str) -> bool:
    return password_hash.verify(plain, hashed)

def create_access_token(data: dict, expires_delta: timedelta | None = None) -> str:
    to_encode = data.copy()
    expire = datetime.now(timezone.utc) + (expires_delta or timedelta(minutes=15))
    to_encode.update({"exp": expire})
    return jwt.encode(to_encode, settings.secret_key, algorithm=settings.algorithm)

async def get_current_user(
    token: Annotated[str, Depends(oauth2_scheme)],
    session: SessionDep,
) -> User:
    credentials_exception = HTTPException(
        status_code=status.HTTP_401_UNAUTHORIZED,
        detail="Could not validate credentials",
        headers={"WWW-Authenticate": "Bearer"},
    )
    try:
        payload = jwt.decode(token, settings.secret_key, algorithms=[settings.algorithm])
        username: str = payload.get("sub")
        if username is None:
            raise credentials_exception
    except jwt.InvalidTokenError:
        raise credentials_exception

    user = session.exec(select(User).where(User.username == username)).first()
    if user is None:
        raise credentials_exception
    return user

async def get_current_active_user(
    current_user: Annotated[User, Depends(get_current_user)],
) -> User:
    if current_user.disabled:
        raise HTTPException(status_code=400, detail="Inactive user")
    return current_user

# Reusable type alias
CurrentUser = Annotated[User, Depends(get_current_active_user)]
```

### Auth Router

```python
from fastapi import APIRouter, Depends
from fastapi.security import OAuth2PasswordRequestForm

router = APIRouter()

@router.post("/token", response_model=Token)
async def login(
    form_data: Annotated[OAuth2PasswordRequestForm, Depends()],
    session: SessionDep,
):
    user = session.exec(select(User).where(User.username == form_data.username)).first()
    if not user or not verify_password(form_data.password, user.hashed_password):
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Incorrect username or password",
        )
    access_token = create_access_token(
        data={"sub": user.username},
        expires_delta=timedelta(minutes=settings.access_token_expire_minutes),
    )
    return Token(access_token=access_token, token_type="bearer")
```

---

## Middleware

### Custom Middleware (Process Time Header)

```python
import time
from starlette.middleware.base import BaseHTTPMiddleware
from starlette.requests import Request

class ProcessTimeMiddleware(BaseHTTPMiddleware):
    async def dispatch(self, request: Request, call_next):
        start = time.perf_counter()
        response = await call_next(request)
        duration = time.perf_counter() - start
        response.headers["X-Process-Time"] = f"{duration:.4f}"
        return response

app.add_middleware(ProcessTimeMiddleware)
```

### Built-in Middleware Stack

```python
from fastapi.middleware.cors import CORSMiddleware
from fastapi.middleware.trustedhost import TrustedHostMiddleware
from fastapi.middleware.gzip import GZipMiddleware

# CORS (always add for frontends)
app.add_middleware(
    CORSMiddleware,
    allow_origins=settings.allowed_origins,
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Trusted hosts (production)
app.add_middleware(
    TrustedHostMiddleware,
    allowed_hosts=["api.example.com", "*.example.com"],
)

# GZip compression
app.add_middleware(GZipMiddleware, minimum_size=1000)
```

---

## Error Handling

```python
from fastapi import Request
from fastapi.responses import JSONResponse

class AppException(Exception):
    def __init__(self, status_code: int, code: str, detail: str):
        self.status_code = status_code
        self.code = code
        self.detail = detail

class NotFoundError(AppException):
    def __init__(self, resource: str, id: str | int):
        super().__init__(404, "NOT_FOUND", f"{resource} with id '{id}' not found")

class ConflictError(AppException):
    def __init__(self, detail: str):
        super().__init__(409, "CONFLICT", detail)

# Global exception handler
@app.exception_handler(AppException)
async def app_exception_handler(request: Request, exc: AppException):
    return JSONResponse(
        status_code=exc.status_code,
        content={"error": {"code": exc.code, "message": exc.detail}},
    )

# Usage in routes
@router.get("/{item_id}", response_model=ItemPublic)
async def get_item(item_id: int, session: SessionDep):
    item = session.get(Item, item_id)
    if not item:
        raise NotFoundError("Item", item_id)
    return item
```

---

## Pydantic V2 Patterns

### Custom Validators

```python
from pydantic import field_validator, model_validator
from sqlmodel import SQLModel

class UserCreate(SQLModel):
    username: str
    email: str
    password: str

    @field_validator("username")
    @classmethod
    def username_alphanumeric(cls, v: str) -> str:
        if not v.isalnum():
            raise ValueError("must be alphanumeric")
        return v.lower()

    @field_validator("password")
    @classmethod
    def password_strength(cls, v: str) -> str:
        if len(v) < 8:
            raise ValueError("must be at least 8 characters")
        if not any(c.isupper() for c in v):
            raise ValueError("must contain an uppercase letter")
        return v

    @model_validator(mode="after")
    def check_username_not_email(self) -> "UserCreate":
        if "@" in self.username:
            raise ValueError("username cannot be an email address")
        return self
```

### Serialization Config

```python
from pydantic import ConfigDict

class ItemPublic(ItemBase):
    id: int
    created_at: datetime

    model_config = ConfigDict(
        from_attributes=True,       # Allow ORM model → Pydantic
        json_encoders={datetime: lambda v: v.isoformat()},
    )
```

---

## Testing

### Test Setup (conftest.py)

```python
import pytest
from fastapi.testclient import TestClient
from sqlmodel import SQLModel, Session, create_engine
from sqlmodel.pool import StaticPool
from app.main import app
from app.database import get_session

@pytest.fixture(name="session")
def session_fixture():
    engine = create_engine(
        "sqlite://",
        connect_args={"check_same_thread": False},
        poolclass=StaticPool,
    )
    SQLModel.metadata.create_all(engine)
    with Session(engine) as session:
        yield session

@pytest.fixture(name="client")
def client_fixture(session: Session):
    def get_session_override():
        return session

    app.dependency_overrides[get_session] = get_session_override
    client = TestClient(app)
    yield client
    app.dependency_overrides.clear()
```

### Test Examples

```python
def test_create_item(client: TestClient):
    response = client.post(
        "/api/v1/items/",
        json={"name": "Widget", "price": 9.99},
        headers={"Authorization": "Bearer test-token"},
    )
    assert response.status_code == 201
    data = response.json()
    assert data["name"] == "Widget"
    assert "id" in data

def test_read_item_not_found(client: TestClient):
    response = client.get("/api/v1/items/999")
    assert response.status_code == 404

def test_create_item_invalid(client: TestClient):
    response = client.post(
        "/api/v1/items/",
        json={"name": "", "price": -1},
    )
    assert response.status_code == 422
```

### Dependency Overrides (Mocking Auth)

```python
from app.modules.auth.dependencies import get_current_active_user

def test_protected_endpoint(client: TestClient, session: Session):
    # Create test user
    test_user = User(username="testuser", email="test@test.com")

    # Override auth dependency
    app.dependency_overrides[get_current_active_user] = lambda: test_user

    response = client.get("/api/v1/items/")
    assert response.status_code == 200

    app.dependency_overrides.clear()
```

---

## Deployment

### Uvicorn Production Config

```bash
# Development
fastapi dev main.py

# Production (with workers)
uvicorn app.main:app --host 0.0.0.0 --port 8000 --workers 4

# With Gunicorn (recommended for production)
gunicorn app.main:app -w 4 -k uvicorn.workers.UvicornWorker --bind 0.0.0.0:8000
```

### Dockerfile

```dockerfile
FROM python:3.12-slim AS base
WORKDIR /app
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt
COPY ./app ./app

FROM base AS production
RUN adduser --disabled-password --no-create-home appuser
USER appuser
EXPOSE 8000
CMD ["gunicorn", "app.main:app", "-w", "4", "-k", "uvicorn.workers.UvicornWorker", "--bind", "0.0.0.0:8000"]
```

### Worker Count Formula

```
workers = (2 × CPU_CORES) + 1
# Example: 4-core server → 9 workers
```

---

## Anti-Patterns

| Anti-Pattern | Problem | Fix |
|-------------|---------|-----|
| `def` for I/O-bound routes | Blocks event loop | Use `async def` for async I/O |
| `async def` for CPU-bound | Still blocks event loop | Use `def` (runs in threadpool) or `run_in_executor` |
| No `response_model` | Exposes internal fields | Always set `response_model=PublicSchema` |
| Single model for table + API | Leaks `hashed_password`, internal IDs | Use multi-model pattern (Base, Create, Update, Public) |
| Creating engine per request | Connection overhead | Create engine once at module level |
| Missing `yield` in session dep | Session never closes | Use `yield` + `with Session(engine)` |
| Hardcoded secrets | Security risk | Use `pydantic-settings` + `.env` |
| No pagination | Unbounded queries | Always add `offset`/`limit` with `Query(le=100)` |
| `@app.on_event("startup")` | Deprecated pattern | Use `lifespan` async context manager |

---

## Sources
- FastAPI Official Docs: https://fastapi.tiangolo.com/
- SQLModel Docs: https://sqlmodel.tiangolo.com/
- Pydantic V2 Docs: https://docs.pydantic.dev/
- Uvicorn Docs: https://www.uvicorn.org/
- Starlette Docs: https://www.starlette.dev/
