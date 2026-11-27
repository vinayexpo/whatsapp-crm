import { CommerceWorkspace } from "~/components/commerce/commerce-workspace";
export function meta() {
  return [{ title: "Locations — Creative Connects" }];
}
export default function CommercePage() {
  return <CommerceWorkspace section="locations" />;
}
