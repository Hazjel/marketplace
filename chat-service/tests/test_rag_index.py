import asyncio

import pytest

from rag import vectorstore


class FakeCollection:
    def __init__(self, ids):
        self.ids = set(ids)

    def upsert(self, ids, documents, metadatas):
        self.ids |= set(ids)

    def get(self, include):
        return {"ids": list(self.ids)}

    def delete(self, ids):
        self.ids -= set(ids)

    def count(self):
        return len(self.ids)


def _store(existing):
    vs = vectorstore.ProductVectorStore.__new__(vectorstore.ProductVectorStore)
    vs._collection = FakeCollection(existing)
    return vs


def test_products_gone_from_the_catalogue_leave_the_index(monkeypatch):
    async def fetch():
        return [{"id": "live", "name": "Laptop"}]

    monkeypatch.setattr(vectorstore, "fetch_all_products", fetch)
    vs = _store({"live", "deactivated-store-product", "deleted-product"})

    assert asyncio.run(vs.build_index()) == 1
    assert vs._collection.ids == {"live"}


def test_a_failed_fetch_leaves_the_index_untouched(monkeypatch):
    async def fetch():
        raise RuntimeError("Laravel HTTP 500")

    monkeypatch.setattr(vectorstore, "fetch_all_products", fetch)
    vs = _store({"a", "b"})

    with pytest.raises(RuntimeError):
        asyncio.run(vs.build_index())
    assert vs._collection.ids == {"a", "b"}
