import uuid

from pydantic import BaseModel, Field, field_validator


class ChatRequest(BaseModel):
    message:    str        = Field(..., min_length=1, max_length=1000)
    session_id: str | None = Field(default=None)

    @field_validator("message")
    @classmethod
    def message_not_blank(cls, v: str) -> str:
        if not v.strip():
            raise ValueError("message cannot be blank")
        return v.strip()

    @field_validator("session_id")
    @classmethod
    def session_id_must_be_uuid(cls, v: str | None) -> str | None:
        # session_id dipakai sebagai key Redis — batasi ke format UUID agar
        # client tidak bisa menebak/memilih key sesi milik orang lain
        if v is None:
            return v
        try:
            uuid.UUID(v)
        except ValueError:
            return None
        return v


class ChatResponse(BaseModel):
    reply:      str
    status:     str
    session_id: str
    products:   list[dict] | None = None   # produk relevan dari RAG (opsional)


class FeedbackRequest(BaseModel):
    session_id: str
    rating:     int        = Field(..., ge=1, le=5)
    comment:    str | None = Field(default=None, max_length=500)


class StoreProduct(BaseModel):
    name:  str
    price: float
    stock: int


class StoreAssistantRequest(BaseModel):
    store_name:     str            = Field(..., min_length=1, max_length=255)
    products:       list[StoreProduct] = Field(default_factory=list)
    buyer_message:  str            = Field(..., min_length=1, max_length=1000)

    @field_validator("buyer_message")
    @classmethod
    def buyer_message_not_blank(cls, v: str) -> str:
        if not v.strip():
            raise ValueError("buyer_message cannot be blank")
        return v.strip()


class StoreAssistantResponse(BaseModel):
    reply: str
