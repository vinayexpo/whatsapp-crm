import { CommerceWorkspace } from "~/components/commerce/commerce-workspace";
export function meta() {
  return [{ title: "Commerce payments — Creative Connects" }];
}
export default function CommercePayments() {
  return <CommerceWorkspace section="payments" />;
}
