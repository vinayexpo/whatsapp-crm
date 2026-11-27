import { CommerceWorkspace } from "~/components/commerce/commerce-workspace";
export function meta() {
  return [{ title: "Reports — Creative Connects" }];
}
export default function CommercePage() {
  return <CommerceWorkspace section="reports" />;
}
