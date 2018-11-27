<?php
// Projet TraceGPS - services web
// fichier : services/GetTousLesUtilisateurs.php
// Derni�re mise � jour : 27/11/2018 par Coubrun

//R�le : ce service web permet � un utilisateur d'obtenir le d�tail d'un de ses parcours ou d'un parcours d'un membre qui l'autorise.

//Param�tres � fournir :
//	pseudo : le pseudo de l'utilisateur
//	mdpSha1 : le mot de passe de l'utilisateur hash� en sha1
//	idTrace : l'id de la trace � consulter
//	dateHeure : la date et l'heure au point de passage (format 'Y-m-d H:i:s')
//	latitude : latitude du point de passage
//	longitude : longitude du point de passage
//	altitude : altitude du point de passage
//	rythmeCardio : rythme cardiaque au point de passage (ou 0 si le rythme n'est pas mesurable)
//	lang : le langage utilis� pour le flux de donn�es ("xml" ou "json")

// Le service retourne un flux de donn�es XML contenant un compte-rendu d'ex�cution ainsi que la synth�se et la liste des points du parcours

// Les param�tres peuvent �tre pass�s par la m�thode GET (pratique pour les tests, mais � �viter en exploitation) :
//     http://<h�bergeur>/GetUnParcoursEtSesPoints.php?pseudo=europa&mdpSha1=13e3668bbee30b004380052b086457b014504b3e&idTrace=2

// Les param�tres peuvent �tre pass�s par la m�thode POST (� privil�gier en exploitation pour la confidentialit� des donn�es) :
//     http://<h�bergeur>/GetUnParcoursEtSesPoints.php

// connexion du serveur web � la base MySQL
include_once ('../modele/DAO/DAO.class.php');
$dao = new DAO();

// R�cup�ration des donn�es transmises
// la fonction $_GET r�cup�re une donn�e pass�e en param�tre dans l'URL par la m�thode GET
// la fonction $_POST r�cup�re une donn�e envoy�es par la m�thode POST
// la fonction $_REQUEST r�cup�re par d�faut le contenu des variables $_GET, $_POST, $_COOKIE
if ( empty ($_REQUEST ["pseudo"]) == true)  $pseudo = "";  else   $pseudo = $_REQUEST ["pseudo"];
if ( empty ($_REQUEST ["mdpSha1"]) == true)  $mdpSha1 = "";  else   $mdpSha1 = $_REQUEST ["mdpSha1"];
if ( empty ($_REQUEST ["idTrace"]) == true)  $idTrace = "";  else   $idTrace = $_REQUEST ["idTrace"];
if ( empty ($_REQUEST ["dateHeure"]) == true)  $dateHeure = "";  else   $dateHeure = $_REQUEST ["dateHeure"];
if ( empty ($_REQUEST ["latitude"]) == true)  $latitude = "";  else   $latitude = $_REQUEST ["latitude"];
if ( empty ($_REQUEST ["longitude"]) == true)  $longitude = "";  else   $longitude = $_REQUEST ["longitude"];
if ( empty ($_REQUEST ["altitude"]) == true)  $altitude = "";  else   $altitude = $_REQUEST ["altitude"];
if ( empty ($_REQUEST ["rythmeCardio"]) == true)  $rythmeCardio = "0";  else   $rythmeCardio = $_REQUEST ["rythmeCardio"];

// initialisation
$laTrace = null;

// Contr�le de la pr�sence des param�tres
if ( $pseudo == "" || $mdpSha1 == "" || $idTrace == "" || $dateHeure == "" || $latitude == "" || $longitude == "" || $altitude == "" || $rythmeCardio == ""  )
{
    $msg = "Erreur : donn�es incompl�tes !";
}
else
{
    if ( $dao->getNiveauConnexion($pseudo, $mdpSha1) == 0 )
    {
        $msg = "Erreur : authentification incorrecte !";
    }
    else
    {	// contr�le d'existence de idTrace
        $laTrace = $dao->getUneTrace($idTrace);
        if ($laTrace == null)
        {
            $msg = "Erreur : le num�ro de trace n'existe pas !";
        }
        else
        {
            // r�cup�ration de l'id de l'utilisateur
            $idUtilisateur = $dao->getUnUtilisateur($pseudo)->getId();
            if ( $idUtilisateur != $laTrace->getIdUtilisateur() )
            {
                $msg = "Erreur : le num�ro de trace ne correspond pas � cet utilisateur !";
            }
            else
            {
                // calcul du num�ro du point
                $idPoint = $laTrace->getNombrePoints() + 1;
                
                // cr�ation du point
                $tempsCumule = 0;
                $distanceCumulee = 0;
                $vitesse = 0;
                $unPoint = new PointDeTrace($idTrace, $idPoint, $latitude, $longitude, $altitude, $dateHeure, $rythmeCardio, $tempsCumule, $distanceCumulee, $vitesse);
                
                // enregistrement du point
                $ok = $dao->creerUnPointDeTrace($unPoint);
                if (! $ok)
                {
                    $msg = "Erreur : probl�me lors de l'enregistrement du point !";
                }// fin if
                else 
                {
                    $msg = "Point cr��.";
                }// fin else 5
                
            }//fin else 4
        }//fin else 3
    }//fin else 2
}//fin else 1

// ferme la connexion � MySQL
unset($dao);

// cr�ation du flux XML en sortie
creerFluxXML ($msg, $unPoint);
// fin du programme (pour ne pas enchainer sur la fonction qui suit)
exit;
// cr�ation du flux XML en sortie
function creerFluxXML($msg, $unPoint)
{
    // cr�e une instance de DOMdocument (DOM : Document Object Model)
    $doc = new DOMDocument();
    // specifie la version et le type d'encodage
    $doc->version = '1.0';
    $doc->encoding = 'UTF-8';
    
    // cr�e un commentaire et l'encode en UTF-8
    $elt_commentaire = $doc->createComment('Service web EnvoyerPosition - BTS SIO - Lyc�e De La Salle - Rennes');
    // place ce commentaire � la racine du document XML
    $doc->appendChild($elt_commentaire);
    
    // cr�e l'�l�ment 'data' � la racine du document XML
    $elt_data = $doc->createElement('data');
    $doc->appendChild($elt_data);
    
    // place l'�l�ment 'reponse' dans l'�l�ment 'data'
    $elt_reponse = $doc->createElement('reponse', $msg);
    $elt_data->appendChild($elt_reponse);
    
    // place l'�l�ment 'donnees' dans l'�l�ment 'data'
    $elt_donnees = $doc->createElement('donnees');
    $elt_data->appendChild($elt_donnees);
    
    if ($unPoint != null)
    {
        // place l'id du point dans l'�l�ment 'donnees'
        $elt_id = $doc->createElement('id', $unPoint->getId());
        $elt_donnees->appendChild($elt_id);
    }// fin if
    
    // Mise en forme finale
    $doc->formatOutput = true;
    
    // renvoie le contenu XML
    echo $doc->saveXML();
    return;
    
}// fin function

?>