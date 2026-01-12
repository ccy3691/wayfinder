import { readFileSync } from "fs";
import { join } from "path";
import { describe, expect, test } from "vitest";

describe("Pagination", () => {
    const typesPath = join(
        __dirname,
        "../workbench/resources/js/wayfinder/types.d.ts"
    );

    test("types.d.ts contains PaginationTest page type", () => {
        const content = readFileSync(typesPath, "utf-8");
        expect(content).toContain("export type PaginationTest");
    });

    test("PaginationTest includes SharedData", () => {
        const content = readFileSync(typesPath, "utf-8");
        expect(content).toContain("PaginationTest = Inertia.SharedData");
    });

    test("PaginationTest includes products with generic LengthAwarePaginator type", () => {
        const content = readFileSync(typesPath, "utf-8");
        expect(content).toContain("products: Illuminate.Pagination.LengthAwarePaginator<App.Models.Product>");
    });

    test("LengthAwarePaginator type is generic", () => {
        const content = readFileSync(typesPath, "utf-8");
        expect(content).toContain("export type LengthAwarePaginator<T>");
    });

    test("LengthAwarePaginator type includes required pagination properties", () => {
        const content = readFileSync(typesPath, "utf-8");
        expect(content).toContain("current_page: number");
        expect(content).toContain("data: T[]");
        expect(content).toContain("first_page_url: string");
        expect(content).toContain("per_page: number");
        expect(content).toContain("next_page_url: string | null");
        expect(content).toContain("prev_page_url: string | null");
        expect(content).toContain("total: number");
    });
});
