# DiviTurnstile

**DiviTurnstile** est un plugin conçu pour protéger vos formulaires Divi contre les spambots et le spam manuel. Il intègre **Cloudflare Turnstile** et **SpamAssassin** pour une protection complète et efficace.

> **Note** : Ce projet est actuellement en **version bêta**. Il est encore en cours de développement et peut contenir des bugs ou des fonctionnalités incomplètes. N'hésitez pas à signaler tout problème ou à contribuer pour améliorer le plugin.

## Fonctionnalités

- **Protection anti-robot** : Utilisation de Cloudflare Turnstile pour bloquer les spambots.
- **Analyse anti-spam avancée** : Intégration avec SpamAssassin pour détecter et filtrer le spam manuel.
- Compatible avec les formulaires Divi.

## Configuration

### Étape 1 : Configurez vos clés Cloudflare Turnstile
1. Rendez-vous sur le tableau de bord Cloudflare pour générer vos clés Turnstile.
2. Ajoutez vos clés dans les paramètres du plugin DiviTurnstile :
   - Clé publique
   - Clé privée

### Étape 2 : Activez l'analyse anti-spam avancée
1. Assurez-vous que SpamAssassin est installé et configuré.
2. Si vous utilisez Docker, démarrez votre conteneur SpamAssassin avec la commande suivante :
   ```bash
   docker run -d --name spamassassin -p 783:783 spamassassin/spamassassin
   ```
3. Vérifiez que SpamAssassin est en cours d'exécution et accessible.

## Prérequis

- **PHP** : Version >= 7.4
- **Docker** (optionnel) : Pour exécuter SpamAssassin dans un conteneur.
- **Cloudflare Turnstile** : Clés API pour l'intégration.

## Installation

1. Clonez ce dépôt dans votre environnement local :
   ```bash
   git clone https://github.com/k4b1s/DiviTurnstile.git
   ```

2. Accédez au répertoire du projet :
   ```bash
   cd DiviTurnstile
   ```

3. Installez le plugin dans WordPress :
   - Compressez les fichiers du projet en un fichier ZIP.
   - Téléchargez le fichier ZIP dans l'interface WordPress sous **Extensions > Ajouter**.

## Utilisation

Une fois installé et configuré :
1. Accédez à vos formulaires Divi.
2. Activez la protection DiviTurnstile dans les paramètres du formulaire.
3. Sauvegardez et testez votre formulaire pour vérifier que la protection fonctionne.

## Contributions

Les contributions sont les bienvenues ! Si vous souhaitez améliorer ce plugin, veuillez suivre ces étapes :
1. Forkez le dépôt.
2. Créez une branche pour votre fonctionnalité :
   ```bash
   git checkout -b feature/nom-de-la-fonctionnalité
   ```
3. Soumettez vos modifications via une Pull Request.

## Licence

Ce projet est sous licence MIT. Voir le fichier [LICENSE](./LICENSE) pour plus de détails.

---

*Créé et maintenu par [k4b1s](https://github.com/k4b1s).*
