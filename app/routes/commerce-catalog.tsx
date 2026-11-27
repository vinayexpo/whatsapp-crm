import { CommerceWorkspace } from "~/components/commerce/commerce-workspace";
export function meta() {
  return [{ title: "Products — Creative Connects" }];
}
export default function CommercePage() {
  return <CommerceWorkspace section="products" />;
}
