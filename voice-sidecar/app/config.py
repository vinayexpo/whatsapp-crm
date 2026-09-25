from pydantic_settings import BaseSettings


class Settings(BaseSettings):
    laravel_base_url: str
    laravel_shared_secret: str
    openai_api_key: str
    coturn_host: str | None = None
    coturn_secret: str | None = None
    port: int = 8080

    model_config = {"env_prefix": ""}


settings = Settings()
