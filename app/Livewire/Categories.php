<?php

namespace App\Livewire;

use App\Models\Category;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title(content: "Categories - ExpenseApp")]

class Categories extends Component
{
    // ----------------------
    // Public Properties (Form State)
    // ----------------------
    // These are reactive Livewire properties bound to inputs in the Blade view.
    public $name = "";
    public $color = "#3B82F6";
    public $icon = "";
    public $editingId = null;
    public $isEditing = false;

    // ----------------------
    // Color Palette
    // ----------------------
    // A fixed list of category color options.
    public $colors = [
        '#EF4444', // Red
        '#F97316', // Orange
        '#F59E0B', // Amber
        '#EAB308', // Yellow
        '#84CC16', // Lime
        '#22C55E', // Green
        '#10B981', // Emerald
        '#14B8A6', // Teal
        '#06B6D4', // Cyan
        '#0EA5E9', // Sky
        '#3B82F6', // Blue
        '#6366F1', // Indigo
        '#8B5CF6', // Violet
        '#A855F7', // Purple
        '#D946EF', // Fuchsia
        '#EC4899', // Pink
        '#F43F5E', // Rose
    ];

    // ----------------------
    // This function defines the validation rules for the form fields
    // It dynamically adjusts rules depending on whether the user is creating or editing a category.
    // ----------------------
    protected function rules()
    {
        return [
            // 🏷️ 'name' field validation, 'required' → Field must not be empty,'string' → Input must be a valid string
            // - 'max:255' → Maximum allowed length is 255 characters
            // - 'unique:categories,name,...' → Ensures the category name is unique for the logged-in user
            //   → Format: unique:<table>,<column>,<ignore_id>,<id_column>,<where_column>,<where_value>
            //   → ($this->editingId ?: 'NULL') → If editing, ignore the current category’s ID to allow updating without conflict
            //   → 'user_id,' . auth()->id() → Ensures uniqueness is checked only for the current user (multi-user safety)
            'name' => 'required|string|max:255|unique:categories,name,' . ($this->editingId ?: 'NULL') . ',id,user_id,' . auth()->id(),

            // 🎨 'color' field validation
            // - 'required' → Must be provided
            // - 'string' → Must be a string (usually a color hex code or CSS color name)
            'color' => 'required|string',

            // 🖼️ 'icon' field validation
            // - 'nullable' → Optional field (can be left empty)
            // - 'string' → Must be a valid string if provided
            // - 'max:255' → Maximum length of 255 characters
            'icon' => 'nullable|string|max:255',
        ];
    }

    // ----------------------
    // This property defines custom validation error messages for form validation.
    // These messages override Laravel's default ones, making them more user-friendly.
    // ----------------------
    protected $messages = [
        // 🏷️ Custom message for when the 'name' field is left empty
        // Shown when validation rule 'name.required' fails
        'name.required' => 'Please enter a category name.',
        'name.unique' => 'You already have a category with this name.',
        'color.required' => 'Please select a color.',
    ];

    // ----------------------
    // Computed Property: categories
    // ----------------------
    // Uses Livewire’s #[Computed] attribute to dynamically fetch all categories
    // for the currently authenticated user. Automatically re-evaluates
    // when dependent data (like new categories) changes.
    #[Computed]
    public function categories()
    {
        return Category::withCount('expenses')
            ->where('user_id', auth()->user()->id)
            ->orderBy('name')
            ->get();
    }

    // ----------------------
    // This function handles loading an existing category into the form for editing.
    // It ensures the category exists, belongs to the logged-in user, and then populates form fields with its data.
    // ----------------------
    public function edit($categoryId)
    {
        // 🔍 Retrieve the category record from the database using its ID
        // If no matching category is found, Laravel automatically throws a 404 (Not Found) error
        $category = Category::findOrFail($categoryId);

        // 🔒 Authorization check — make sure the category belongs to the logged-in user
        // Prevents users from editing other users' categories (security measure)
        if ($category->user_id !== auth()->id()) {
            abort(403);
        }

        // ✏️ Store the category’s ID in the Livewire component for reference while editing
        $this->editingId = $category->id;

        // 🏷️ Fill the form fields with the existing category’s data
        // These properties are bound to input fields in the Livewire component
        $this->name = $category->name; // Pre-fill category name
        $this->color = $category->color;
        $this->icon = $category->icon;

        // 🛠️ Set the editing mode flag to true
        // This tells the component that we are editing an existing record, not creating a new one
        $this->isEditing = true;
    }

    // ----------------------
    // This function handles both creating and updating categories.
    // It validates inputs, checks whether the user is editing or creating, ensures authorization, performs the save/update, flashes a success message, and resets the form for the next action.
    // ----------------------
    public function save()
    {
        // ✅ Validate all form inputs using the validation rules defined in this Livewire component
        $this->validate();

        // ✅ Check if we are editing an existing category (not creating a new one)
        if ($this->isEditing && $this->editingId) {
            // 🔍 Find the category record by ID, or fail if it doesn’t exist
            $category = Category::findOrFail($this->editingId);

            // 🔒 Security check: make sure the category belongs to the logged-in user
            // Prevents unauthorized editing of other users' categories
            if ($category->user_id !== auth()->user()->id) {
                abort(403);
            }

            // ✏️ Update the existing category with the new form data
            $category->update([
                'name' => $this->name,
                'color' => $this->color,
                'icon' => $this->icon,
            ]);
            // 💬 Show a success message to the user after updating
            session()->flash('message', 'Category updated successfully.');
        } else {
            // 🆕 Creating a new category when not in edit mode
            Category::create([
                'user_id' => auth()->id(), // Assign category to the logged-in user
                'name' => $this->name, // Category name from the form
                'color' => $this->color, // Selected color
                'icon' => $this->icon, // Optional icon
            ]);
            // 💬 Show a success message after creating a new category
            session()->flash('message', 'Category created successfully.');
        }
        // 🔁 Reset the form fields and state so the form is cleared after saving
        $this->reset(['name', 'color', 'icon', 'editingId', 'isEditing']);
    }

    // ----------------------
    // This function cancels the edit operation and resets the form to its default state.
    // It clears all input fields and restores default values, ensuring a clean form for the next action.
    // ----------------------
    public function cancelEdit()
    {
        // 🔁 Reset specific component properties back to their initial state, Clears all form inputs and internal tracking variables
        $this->reset(['name', 'color', 'icon', 'editingId', 'isEditing']);
        $this->color = "#3B82F6";
    }

    // ----------------------
    // This function handles deleting a category safely and securely.
    // It ensures that the category exists, belongs to the logged-in user, 
    // and does not contain any associated expenses before deletion.
    // ----------------------
    public function delete($categoryId)
    {
        // 🔍 Find the category by its ID
        // If it doesn't exist, Laravel automatically throws a 404 (Not Found) exception
        $category = Category::findOrFail($categoryId);

        // 🔒 Authorization check — verify that the category belongs to the current logged-in user
        // Prevents users from deleting other users’ categories (security best practice)
        if ($category->user_id !== auth()->user()->id) {
            abort(403);
        }

        // 💰 Check if the category has any associated expenses before deletion
        // Using the relationship method expenses(), count the related expense records
        // If one or more exist, prevent deletion and show an error message
        if ($category->expenses()->count() > 0) {
            session()->flash('error', 'Can not delete category with existing expenses.');
            return;
        }

        // 🗑️ Proceed with deleting the category since it’s safe to do so
        $category->delete();
        // 💬 Flash a success message to inform the user of the successful deletion
        session()->flash('message', 'Category deleted successfully!');
    }

    // ----------------------
    // Render Method
    // ----------------------
    // Renders the corresponding Blade view and passes reactive data to it.
    public function render()
    {
        return view('livewire.categories', [
            'categories' => $this->categories,
        ]);
    }
}
