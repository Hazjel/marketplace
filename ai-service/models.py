from pydantic import BaseModel, Field


class ChatRequest(BaseModel):
    message:    str        = Field(..., min_length=1, max_length=1000)
    session_id: str | None = Field(default=None)


class ChatResponse(BaseModel):
    reply:      str
    status:     str
    session_id: str
    products:   list[dict] | None = None   # produk relevan dari RAG (opsional)


class FeedbackRequest(BaseModel):
    session_id: str
    rating:     int        = Field(..., ge=1, le=5)
    comment:    str | None = Field(default=None, max_length=500)
