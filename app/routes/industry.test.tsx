// @vitest-environment jsdom
import { afterEach, describe, expect, it } from "vitest";
import { cleanup, fireEvent, render, screen, within } from "@testing-library/react";
import { MemoryRouter, Route, Routes, useLocation } from "react-router";
import IndustryPage, { meta } from "./industry";
import Industries from "./industries";
import { industries, industryAliases } from "~/data/industries";

afterEach(cleanup);
function LocationProbe() {
  return <output data-testid="location">{useLocation().pathname}</output>;
}
function open(path: string) {
  return render(
    <MemoryRouter initialEntries={[path]}>
      <Routes>
        <Route path="/industries" element={<Industries />} />
        <Route
          path="/industries/:sector"
          element={
            <>
              <IndustryPage />
              <LocationProbe />
            </>
          }
        />
      </Routes>
    </MemoryRouter>,
  );
}
describe("public industry pages", () => {
  it.each(Object.entries(industryAliases))("redirects legacy %s links to %s", (legacy, canonical) => {
    open(`/industries/${legacy}`);
    expect(screen.getByTestId("location").textContent).toBe(`/industries/${canonical}`);
    expect(screen.getByRole("heading", { level: 1 }).textContent).toBe(
      industries.find((item) => item.slug === canonical)!.headline,
    );
  });
  it("changes the directory motion preview when another industry is chosen", () => {
    open("/industries");
    fireEvent.change(screen.getByLabelText("Preview an industry"), { target: { value: "banking-fintech" } });
    expect(screen.getByRole("figure", { name: "Banking & Fintech illustrative workflow" })).toBeTruthy();
    expect(screen.getByText("Where can I learn about your services?")).toBeTruthy();
  });
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
    open("/industries/education-edtech");
    const first = screen.getByRole("tab", { name: "Admissions questions" });
    fireEvent.keyDown(first, { key: "End" });
    expect(screen.getByRole("tab", { name: "Course updates" }).getAttribute("aria-selected")).toBe("true");
    expect(document.activeElement).toBe(screen.getByRole("tab", { name: "Course updates" }));
    fireEvent.click(screen.getByRole("link", { name: /Explore healthcare/ }));
    expect(screen.getByRole("tab", { name: "Service information" }).getAttribute("aria-selected")).toBe("true");
    expect(screen.getByRole("tabpanel").textContent).toContain("Where is your centre");
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
