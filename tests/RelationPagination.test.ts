import { readFileSync } from "fs";
import { join } from "path";
import { describe, expect, test } from "vitest";

describe("Relation Pagination", () => {
    const typesPath = join(
        __dirname,
        "../workbench/resources/js/wayfinder/types.d.ts"
    );

    test("types.d.ts contains RelationPaginationTest page type", () => {
        const content = readFileSync(typesPath, "utf-8");
        expect(content).toContain("export type RelationPaginationTest");
    });

    test("RelationPaginationTest includes SharedData", () => {
        const content = readFileSync(typesPath, "utf-8");
        expect(content).toContain("RelationPaginationTest = Inertia.SharedData");
    });

    test("RelationPaginationTest includes owned with generic LengthAwarePaginator for Product", () => {
        const content = readFileSync(typesPath, "utf-8");
        expect(content).toContain("owned: Illuminate.Pagination.LengthAwarePaginator<App.Models.Product>");
    });

    test("RelationPaginationTest includes favoritesSimple with generic Paginator for Category", () => {
        const content = readFileSync(typesPath, "utf-8");
        expect(content).toContain("favoritesSimple: Illuminate.Contracts.Pagination.Paginator<App.Models.Category>");
    });

    test("ChainedRelationPaginationTest includes chained with generic LengthAwarePaginator for Product", () => {
        const content = readFileSync(typesPath, "utf-8");
        expect(content).toContain("export type ChainedRelationPaginationTest");
        expect(content).toContain("chained: Illuminate.Pagination.LengthAwarePaginator<App.Models.Product>");
    });
});
