// @vitest-environment jsdom
import { afterEach, describe, expect, it } from "vitest";
import { cleanup, fireEvent, render, screen, within } from "@testing-library/react";
import { MemoryRouter, Route, Routes } from "react-router";
import IndustryPage, { meta } from "./industry";
import Industries from "./industries";
import { industries } from "~/data/industries";

afterEach(cleanup);
function open(path: string) {
  return render(
    <MemoryRouter initialEntries={[path]}>
      <Routes>
        <Route path="/industries" element={<Industries />} />
        <Route path="/industries/:sector" element={<IndustryPage />} />
      </Routes>
    </MemoryRouter>,
  );
}
describe("public industry pages", () => {
  it("links every sector from the directory to a distinct route", () => {
    open("/industries");
    for (const industry of industries)
      expect(
        screen.getByRole("link", { name: new RegExp(`Explore ${industry.name.toLowerCase()}`) }).getAttribute("href"),
      ).toBe(`/industries/${industry.slug}`);
  });
  it.each(industries)("renders $name with its own sample and page metadata", (industry) => {
    open(`/industries/${industry.slug}`);
    expect(screen.getByRole("heading", { level: 1, name: industry.headline })).toBeTruthy();
    expect(within(screen.getByRole("tabpanel")).getByText(industry.scenarios[0].question)).toBeTruthy();
    const tags = meta({ params: { sector: industry.slug } } as Parameters<typeof meta>[0]);
    expect(tags).toContainEqual({ name: "description", content: industry.description });
  });
  it("switches examples with arrow keys and resets the selected scenario on sector navigation", () => {
    open("/industries/schools");
    const first = screen.getByRole("tab", { name: "Admissions questions" });
    fireEvent.keyDown(first, { key: "End" });
    expect(screen.getByRole("tab", { name: "School updates" }).getAttribute("aria-selected")).toBe("true");
    expect(document.activeElement).toBe(screen.getByRole("tab", { name: "School updates" }));
    fireEvent.click(screen.getByRole("link", { name: /Explore clinics/ }));
    expect(screen.getByRole("tab", { name: "Clinic information" }).getAttribute("aria-selected")).toBe("true");
    expect(screen.getByRole("tabpanel").textContent).toContain("Where is the clinic");
  });
  it("offers a safe directory path and noindex for an unknown industry", () => {
    open("/industries/unknown");
    expect(screen.getByRole("link", { name: "Browse industries" }).getAttribute("href")).toBe("/industries");
    expect(meta({ params: { sector: "unknown" } } as Parameters<typeof meta>[0])).toContainEqual({
      name: "robots",
      content: "noindex",
    });
  });
});
