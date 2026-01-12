import { readFileSync } from "fs";
import { join } from "path";
import { describe, expect, test } from "vitest";

describe("Simple Pagination", () => {
    const typesPath = join(
        __dirname,
        "../workbench/resources/js/wayfinder/types.d.ts"
    );

    test("types.d.ts contains SimplePaginationTest page type", () => {
        const content = readFileSync(typesPath, "utf-8");
        expect(content).toContain("export type SimplePaginationTest");
    });

    test("SimplePaginationTest includes SharedData", () => {
        const content = readFileSync(typesPath, "utf-8");
        expect(content).toContain("SimplePaginationTest = Inertia.SharedData");
    });

    test("SimplePaginationTest includes productsSimple with generic Paginator type", () => {
        const content = readFileSync(typesPath, "utf-8");
        expect(content).toContain("productsSimple: Illuminate.Contracts.Pagination.Paginator<App.Models.Product>");
    });

    test("Paginator type is generic", () => {
        const content = readFileSync(typesPath, "utf-8");
        expect(content).toContain("export type Paginator<T>");
    });
});
