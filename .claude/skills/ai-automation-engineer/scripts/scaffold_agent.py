#!/usr/bin/env python3
"""
Scaffold an AI agent project with production-ready structure.

Usage:
    python scaffold_agent.py <project_name> [--pattern <pattern>] [--framework <framework>]

Patterns: react, plan-execute, router, chain, crew, supervisor
Frameworks: anthropic, langchain, crewai, custom

Example:
    python scaffold_agent.py my-agent --pattern react --framework anthropic
"""

import argparse
import os
import sys
from pathlib import Path

PATTERNS = ["react", "plan-execute", "router", "chain", "crew", "supervisor"]
FRAMEWORKS = ["anthropic", "langchain", "crewai", "custom"]


def create_directory_structure(base_path: Path, pattern: str, framework: str):
    """Create project directory structure."""
    dirs = [
        "src",
        "src/agents",
        "src/tools",
        "src/prompts",
        "src/models",
        "src/utils",
        "tests",
        "tests/evals",
        "tests/unit",
        "tests/integration",
        "config",
        "scripts",
        "docs",
    ]

    for d in dirs:
        (base_path / d).mkdir(parents=True, exist_ok=True)

    print(f"  Created {len(dirs)} directories")


def create_env_template(base_path: Path, framework: str):
    """Create .env.example file."""
    env_vars = {
        "anthropic": [
            "ANTHROPIC_API_KEY=your-api-key-here",
            "MODEL_NAME=claude-sonnet-4-5-20250929",
            "MAX_TOKENS=4096",
        ],
        "langchain": [
            "ANTHROPIC_API_KEY=your-api-key-here",
            "LANGCHAIN_API_KEY=your-langsmith-key-here",
            "LANGCHAIN_TRACING_V2=true",
            "LANGCHAIN_PROJECT=my-project",
        ],
        "crewai": [
            "ANTHROPIC_API_KEY=your-api-key-here",
            "MODEL_NAME=anthropic/claude-sonnet-4-5-20250929",
        ],
        "custom": [
            "LLM_PROVIDER=anthropic",
            "LLM_API_KEY=your-api-key-here",
            "LLM_MODEL=claude-sonnet-4-5-20250929",
        ],
    }

    common = [
        "",
        "# Observability",
        "LOG_LEVEL=INFO",
        "TRACING_ENABLED=true",
        "",
        "# Rate Limits",
        "MAX_REQUESTS_PER_MINUTE=30",
        "DAILY_BUDGET_USD=50.00",
        "",
        "# Cache",
        "REDIS_URL=redis://localhost:6379",
        "CACHE_TTL_HOURS=24",
    ]

    content = ["# Environment Configuration", "# Copy to .env and fill in values", ""]
    content.extend(env_vars.get(framework, env_vars["custom"]))
    content.extend(common)

    (base_path / ".env.example").write_text("\n".join(content))
    (base_path / ".gitignore").write_text(
        "\n".join([".env", "__pycache__/", "*.pyc", ".venv/", "dist/", "*.egg-info/", ".pytest_cache/"])
    )


def create_requirements(base_path: Path, framework: str):
    """Create requirements.txt."""
    common = [
        "pydantic>=2.0",
        "structlog>=23.0",
        "python-dotenv>=1.0",
        "tenacity>=8.0",
        "httpx>=0.24",
    ]

    framework_deps = {
        "anthropic": ["anthropic>=0.40"],
        "langchain": ["langchain>=0.3", "langchain-anthropic>=0.3", "langgraph>=0.2"],
        "crewai": ["crewai>=0.80", "crewai-tools>=0.14"],
        "custom": ["anthropic>=0.40"],
    }

    deps = framework_deps.get(framework, []) + common
    (base_path / "requirements.txt").write_text("\n".join(sorted(deps)))


def create_main_agent(base_path: Path, pattern: str, framework: str):
    """Create main agent file based on pattern."""
    templates = {
        "react": '''"""ReAct Agent - Reason and Act iteratively."""
import anthropic
from src.utils.tracing import trace_llm_call
from src.utils.config import settings

client = anthropic.Anthropic()


async def run_agent(user_input: str, tools: list, max_iterations: int = 10) -> str:
    """Run ReAct agent loop."""
    messages = [{"role": "user", "content": user_input}]

    for i in range(max_iterations):
        response = await trace_llm_call(
            client.messages.create,
            model=settings.model_name,
            max_tokens=settings.max_tokens,
            system="You are a helpful assistant. Think step by step before acting.",
            tools=tools,
            messages=messages,
        )

        if response.stop_reason == "tool_use":
            tool_blocks = [b for b in response.content if b.type == "tool_use"]
            messages.append({"role": "assistant", "content": response.content})

            tool_results = []
            for block in tool_blocks:
                result = await execute_tool(block.name, block.input)
                tool_results.append({
                    "type": "tool_result",
                    "tool_use_id": block.id,
                    "content": str(result),
                })

            messages.append({"role": "user", "content": tool_results})
        else:
            return next(
                (b.text for b in response.content if b.type == "text"),
                "No response generated.",
            )

    return "Max iterations reached. Please refine your request."
''',
        "router": '''"""Router Agent - Classify and route to specialists."""
from pydantic import BaseModel
from enum import Enum


class RouteType(str, Enum):
    # Define your routes here
    ROUTE_A = "route_a"
    ROUTE_B = "route_b"
    DEFAULT = "default"


class RouteDecision(BaseModel):
    route: RouteType
    confidence: float
    reasoning: str


async def route_request(user_input: str) -> str:
    """Classify input and route to appropriate handler."""
    decision = await classify(user_input)

    handlers = {
        RouteType.ROUTE_A: handle_route_a,
        RouteType.ROUTE_B: handle_route_b,
        RouteType.DEFAULT: handle_default,
    }

    handler = handlers.get(decision.route, handle_default)
    return await handler(user_input, decision)
''',
        "chain": '''"""Chain Agent - Sequential LLM processing pipeline."""


async def run_chain(input_data: str) -> dict:
    """Execute sequential processing chain."""
    # Step 1: Analyze
    analysis = await analyze_step(input_data)

    # Step 2: Process
    processed = await process_step(analysis)

    # Step 3: Validate
    validated = await validate_step(processed)

    # Step 4: Format output
    output = await format_step(validated)

    return output
''',
    }

    agent_code = templates.get(pattern, templates["react"])
    (base_path / "src" / "agents" / "main_agent.py").write_text(agent_code)


def create_config(base_path: Path):
    """Create configuration module."""
    config_code = '''"""Application configuration."""
from pydantic_settings import BaseSettings


class Settings(BaseSettings):
    """Application settings loaded from environment."""

    # LLM
    anthropic_api_key: str = ""
    model_name: str = "claude-sonnet-4-5-20250929"
    max_tokens: int = 4096
    temperature: float = 0.0

    # Observability
    log_level: str = "INFO"
    tracing_enabled: bool = True

    # Rate Limits
    max_requests_per_minute: int = 30
    daily_budget_usd: float = 50.0

    # Cache
    redis_url: str = "redis://localhost:6379"
    cache_ttl_hours: int = 24

    class Config:
        env_file = ".env"


settings = Settings()
'''
    (base_path / "src" / "utils" / "config.py").write_text(config_code)
    (base_path / "src" / "__init__.py").write_text("")
    (base_path / "src" / "agents" / "__init__.py").write_text("")
    (base_path / "src" / "tools" / "__init__.py").write_text("")
    (base_path / "src" / "prompts" / "__init__.py").write_text("")
    (base_path / "src" / "models" / "__init__.py").write_text("")
    (base_path / "src" / "utils" / "__init__.py").write_text("")
    (base_path / "tests" / "__init__.py").write_text("")


def create_tracing_utils(base_path: Path):
    """Create tracing utilities."""
    tracing_code = '''"""Tracing and observability utilities."""
import time
import structlog
import functools

logger = structlog.get_logger()


def trace_llm_call(func, **kwargs):
    """Trace an LLM call with timing and token counting."""
    start = time.monotonic()
    try:
        result = func(**kwargs)
        elapsed = (time.monotonic() - start) * 1000

        logger.info("llm_call",
            model=kwargs.get("model", "unknown"),
            input_tokens=getattr(getattr(result, "usage", None), "input_tokens", 0),
            output_tokens=getattr(getattr(result, "usage", None), "output_tokens", 0),
            latency_ms=round(elapsed),
            stop_reason=getattr(result, "stop_reason", "unknown"),
        )
        return result
    except Exception as e:
        elapsed = (time.monotonic() - start) * 1000
        logger.error("llm_call_failed",
            model=kwargs.get("model", "unknown"),
            error=str(e),
            latency_ms=round(elapsed),
        )
        raise
'''
    (base_path / "src" / "utils" / "tracing.py").write_text(tracing_code)


def main():
    parser = argparse.ArgumentParser(description="Scaffold an AI agent project")
    parser.add_argument("project_name", help="Name of the project")
    parser.add_argument("--pattern", choices=PATTERNS, default="react", help="Agent pattern")
    parser.add_argument("--framework", choices=FRAMEWORKS, default="anthropic", help="Framework")
    parser.add_argument("--path", default=".", help="Base output directory")

    args = parser.parse_args()

    base_path = Path(args.path) / args.project_name

    if base_path.exists():
        print(f"Error: Directory '{base_path}' already exists.")
        sys.exit(1)

    print(f"Scaffolding AI agent project: {args.project_name}")
    print(f"  Pattern: {args.pattern}")
    print(f"  Framework: {args.framework}")
    print()

    base_path.mkdir(parents=True)
    create_directory_structure(base_path, args.pattern, args.framework)
    create_env_template(base_path, args.framework)
    create_requirements(base_path, args.framework)
    create_config(base_path)
    create_main_agent(base_path, args.pattern, args.framework)
    create_tracing_utils(base_path)

    print(f"\nProject scaffolded at: {base_path}")
    print(f"\nNext steps:")
    print(f"  1. cd {args.project_name}")
    print(f"  2. cp .env.example .env  # Fill in your API keys")
    print(f"  3. pip install -r requirements.txt")
    print(f"  4. Start building your agent in src/agents/main_agent.py")


if __name__ == "__main__":
    main()
