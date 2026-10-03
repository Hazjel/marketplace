"""
Labeled buyer questions for the chatbot's routing and price parsing.

The router decides whether a question is answered with the catalogue (RAG) or
without it; a wrong "general" sends the LLM in blind. Before 2026-10-03 it got
9 of these 15 right: anything outside a hardcoded list of gadget words skipped
the catalogue. Add a line here whenever a real question is routed wrong.
"""
import pytest

from nlp.intent import extract_metadata_filters, is_general_query

ROUTING = [
    # (question, answered without the catalogue)
    ("halo", True),
    ("terima kasih!", True),
    ("apakah blukios aman?", True),
    ("bagaimana cara pembayaran?", True),
    ("cara refund gimana", True),
    ("cari laptop gaming", False),
    ("harga asus rog berapa?", False),
    ("ada hp samsung murah?", False),
    ("rekomendasi headset bluetooth", False),
    ("ada skincare buat kulit berminyak?", False),
    ("jual kemeja pria?", False),
    ("ada sepatu lari?", False),
    ("tws yang bagus apa", False),
    ("smartwatch di bawah 1 juta", False),
    ("baju anak", False),
    ("bagaimana cara beli laptop", False),
]


@pytest.mark.parametrize(("question", "general"), ROUTING)
def test_routing(question, general):
    assert is_general_query(question) is general


PRICES = [
    ("laptop di bawah 5 juta", [{"price": {"$lte": 5_000_000}}]),
    ("kemeja 100-200 ribu", [{"price": {"$gte": 100_000}}, {"price": {"$lte": 200_000}}]),
    ("hp 2-3jt", [{"price": {"$gte": 2_000_000}}, {"price": {"$lte": 3_000_000}}]),
    # Model numbers are not prices.
    ("iphone 13 sampai 15", []),
    ("rtx 3060 - 4060", []),
]


@pytest.mark.parametrize(("question", "expected"), PRICES)
def test_price_filters(question, expected):
    found = extract_metadata_filters(question)
    clauses = [] if found is None else found.get("$and", [found])
    assert [c for c in clauses if "price" in c] == expected
