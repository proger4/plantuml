import type { Meta, StoryObj } from "@storybook/react";
import { TopBar } from "../layout/TopBar";
import { DocumentSidebar } from "../layout/DocumentSidebar";

const meta: Meta = {
  title: "Studio/Layout",
};

export default meta;

type Story = StoryObj;

export const Chrome: Story = {
  render: () => (
    <div className="h-screen bg-paper">
      <TopBar docId={1} revision={3} lockUserId={1} meId={1} userName="ivan" onSave={() => {}} onRender={() => {}} />
      <div className="h-[calc(100vh-56px)] w-80">
        <DocumentSidebar
          activeId={1}
          docs={[
            { id: 1, author_id: 1, unique_slug: "seed-doc", is_public: 1, status: "valid", current_revision: 3, code: "", is_deleted: 0 },
            { id: 2, author_id: 1, unique_slug: "api-map", is_public: 0, status: "valid", current_revision: 1, code: "", is_deleted: 0 },
          ]}
          onPick={() => {}}
        />
      </div>
    </div>
  ),
};
