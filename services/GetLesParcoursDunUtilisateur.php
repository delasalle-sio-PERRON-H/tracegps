<?php
// Projet TraceGPS - services web
// fichier : services/GetTousLesUtilisateurs.php
// Derni�re mise � jour : 27/11/2018 par Coubrun

//R�le : ce service web permet � un utilisateur d'obtenir le d�tail d'un de ses parcours ou d'un parcours d'un membre qui l'autorise.
    
//Param�tres � fournir :
//	pseudo : le pseudo de l'utilisateur
//	mdpSha1 : le mot de passe de l'utilisateur hash� en sha1
//	idTrace : l'id de la trace � consulter
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
// initialisation
$laTrace = null;
// Contr�le de la pr�sence des param�tres
if ( $pseudo == "" || $mdpSha1 == "" || $idTrace == "" )
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
        $msg = "Erreur : parcours inexistant !";
    }
    else
    {   
        // r�cup�ration de l'id de l'utilisateur demandeur et du propri�taire du parcours
        $idDemandeur = $dao->getUnUtilisateur($pseudo)->getId();
        $idProprietaire = $laTrace->getIdUtilisateur();
        
        // v�rification de l'autorisation
        if ( $idDemandeur != $idProprietaire && $dao->autoriseAConsulter($idProprietaire, $idDemandeur) == false )
        {   
            $msg = "Erreur : vous n'�tes pas autoris� par le propri�taire du parcours !";
        }
        else
        {   
            $msg = "Donn�es de la trace demand�e.";
        }
    }
}
}
// ferme la connexion � MySQL
unset($dao);

// cr�ation du flux XML en sortie
creerFluxXML ($msg, $laTrace);
// fin du programme (pour ne pas enchainer sur la fonction qui suit)
exit;

// cr�ation du flux XML en sortie
function creerFluxXML($msg, $laTrace)
{
    // cr�e une instance de DOMdocument (DOM : Document Object Model)
    $doc = new DOMDocument();
    
    //specifie la version et le type d'encodage
    $doc->version = '1.0';
    $doc->encoding = "UTF-8";
    
    // cr�e un commentaire et l'encode en UTF-8
    $elt_commentaire = $doc->createComment('Service web GetUnParcoursEtSesPoints - BTS SIO - Lyc�e De La Salle - Rennes');
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
    
    if ($laTrace != null)
    {
        // place l'�l�ment 'trace' dans l'�l�ment 'donnees'
        $elt_trace = $doc->createElement('trace');
        $elt_donnees->appendChild($elt_trace);
        
        // place la description de la trace dans l'�l�ment 'trace'
        $elt_id = $doc->createElement('id', $laTrace->getId());
        $elt_trace->appendChild($elt_id);
        
        $elt_dateHeureDebut = $doc->createElement('dateHeureDebut', $laTrace->getId());
        $elt_trace->appendChild($elt_dateHeureDebut);
        
        $elt_terminee = $doc->createElement('terminee', $laTrace->getId());
        $elt_trace->appendChild($elt_terminee);
        
        if ($laTrace->getTerminee() == true)
        {
            $elt_dateHeureFin = $doc->createElement('dateHeureFin', $laTrace->getDateHeureFin());
            $elt_trace->appendChild($elt_dateHeureFin);
        } // fin if
        
        $elt_idUtilisateur = $doc->createElement('idUtilisateur', $laTrace->getIdUtilisateur());
        $elt_trace->appendChild($elt_idUtilisateur);
        
        // place l'�l�ment 'lespoints' dans l'�l�ment 'donnees'
        $elt_lespoints = $doc->createElement('lespoints');
        $elt_donnees->appendChild($elt_lespoints);
        
        // traitement des points
        if (sizeof($laTrace->getLesPointsDeTrace()) > 0)
        {
            foreach ($laTrace->getLesPointsDeTrace() as $unPointDeTrace)
            {
                // cr�e un �l�ment vide 'point'
                $elt_point = $doc->createElement('point');
                // place l'�l�ment 'point' dans l'�l�ment 'lespoints'
                $elt_lespoints->appendChild($elt_point);
                
                // cr�e les �l�ments enfants de l'�l�ment 'point'
                $elt_id             = $doc->createElement('id', $unPointDeTrace->getId());
                $elt_point->appendChild($elt_id);
                
                $elt_latitude       = $doc->createElement('latitude', $unPointDeTrace->getLatitude());
                $elt_point->appendChild($elt_latitude);
                
                $elt_longitude      = $doc->createElement('longitude', $unPointDeTrace->getLongitude());
                $elt_point->appendChild($elt_longitude);
                
                $elt_altitude       = $doc->createElement('altitude', $unPointDeTrace->getAltitude());
                $elt_point->appendChild($elt_altitude);
                
                $elt_dateHeure      = $doc->createElement('dateHeure', $unPointDeTrace->getDateHeure());
                $elt_point->appendChild($elt_dateHeure);
                
                $elt_rythmeCardio       = $doc->createElement('rythmeCardio', $unPointDeTrace->getRythmeCardio());
                $elt_point->appendChild($elt_rythmeCardio);
                
            }// fin foreach
        }// fin if
        
    } // fin if
    // Mise en forme finale
    $doc->formatOutput = true;
    
    // renvoie le contenu XML
    echo $doc->saveXML();
    return;
    
} // fin function



?>