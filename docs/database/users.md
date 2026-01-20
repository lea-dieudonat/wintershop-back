# Users Domain

## User

### Fields

-   **email**: unique, obligatoire, format email
-   **password**: hashé (obligatoire)
-   **roles**: JSON, `ROLE_USER` est toujours ajouté automatiquement
-   **firstName / lastName**: obligatoires
-   **createdAt / updatedAt**: horodatage de création/mise à jour

### Relations

-   **cart**: `OneToOne(Cart)` (panier créé automatiquement à l'inscription)
-   **addresses**: `OneToMany(Address)`
-   **orders**: `OneToMany(Order)`

### Business Rules

-   Un utilisateur a toujours au moins `ROLE_USER`
-   Un seul panier actif par utilisateur (relation 1–1)
-   Les adresses et commandes sont liées à l'utilisateur

## Address

### Fields

-   **street, city, postalCode, country**: obligatoires
-   **firstName, lastName, phoneNumber**: informations de contact obligatoires
-   **additionalInfo**: optionnel
-   **isDefault**: booléen, indique l'adresse par défaut
-   **createdAt / updatedAt**: timestamps gérés par callbacks

### Relations

-   **user**: `ManyToOne(User)` (obligatoire)

### Business Rules

-   **Adresse par défaut unique**: si `isDefault=true`, toutes les autres adresses de l'utilisateur sont passées à `false` (géré dans `AddressProcessor::unsetOtherDefaultAddresses`)
-   Utilisée comme pré-remplissage pour le checkout (shipping/billing)

### API (sécurisé `ROLE_USER`)

-   **GET** `/api/addresses` (liste)
-   **GET** `/api/addresses/{id}` (détail)
-   **POST** `/api/addresses`
-   **PUT/PATCH** `/api/addresses/{id}`
-   **DELETE** `/api/addresses/{id}`

### Cascade / Lifecycle

-   Les adresses sont supprimées quand l'utilisateur est supprimé (relation owning side)
-   Les timestamps sont mis à jour en `PrePersist` / `PreUpdate`
