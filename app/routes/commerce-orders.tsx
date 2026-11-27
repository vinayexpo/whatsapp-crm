import { CommerceWorkspace } from "~/components/commerce/commerce-workspace";
export function meta() {
  return [{ title: "Orders — Creative Connects" }];
}
export default function CommercePage() {
  return <CommerceWorkspace section="orders" />;
}
